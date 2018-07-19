<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;

/**
 * @license GNU GPL v2+
 */
class UpdateDonorUseCase {

	private $authorizationService;
	private $donationRepository;
	private $updateDonorValidator;
	private $donationConfirmationMailer;

	public function __construct(
		DonationAuthorizer $authorizationService,
		UpdateDonorValidator $updateDonorValidator,
		DonationRepository $donationRepository,
		DonationConfirmationMailer $donationConfirmationMailer
	) {
		$this->authorizationService = $authorizationService;
		$this->donationRepository = $donationRepository;
		$this->updateDonorValidator = $updateDonorValidator;
		$this->donationConfirmationMailer = $donationConfirmationMailer;
	}

	public function updateDonor( UpdateDonorRequest $updateDonorRequest ): UpdateDonorResponse {
		if ( !$this->requestIsAllowed( $updateDonorRequest ) ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_ACCESS_DENIED );
		}

		// No null check needed here, because authorizationService will deny access to non-existing donations
		$donation = $this->donationRepository->getDonationById( $updateDonorRequest->getDonationId() );

		if ( $donation->isExported() ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_DONATION_IS_EXPORTED );
		}

		if ( $donation->getDonor() !== null ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_DONATION_HAS_ADDRESS );
		}

		$validationResult = $this->updateDonorValidator->validateDonorData( $updateDonorRequest );
		if ( $validationResult->getViolations() ) {
			// We don't need to return the full validation result since we rely on the client-side validation to catch
			// invalid input and don't output individual field violations in the PHP template
			return UpdateDonorResponse::newFailureResponse( $validationResult->getFirstViolation()->getMessageIdentifier() );
		}

		$donor = new Donor(
			$this->getDonorNameFromRequest( $updateDonorRequest ),
			$this->getDonorAddressFromRequest( $updateDonorRequest ),
			$updateDonorRequest->getEmailAddress()
		);

		$donation->setDonor( $donor );
		$this->donationRepository->storeDonation( $donation );

		$this->donationConfirmationMailer->sendConfirmationMailFor( $donation );

		return UpdateDonorResponse::newSuccessResponse( UpdateDonorResponse::SUCCESS_TEXT, $donation );
	}

	private function getDonorAddressFromRequest( UpdateDonorRequest $updateDonorRequest ): DonorAddress {
		$donorAddress = new DonorAddress();
		$donorAddress->setCity( $updateDonorRequest->getCity() );
		$donorAddress->setPostalCode( $updateDonorRequest->getPostalCode() );
		$donorAddress->setStreetAddress( $updateDonorRequest->getStreetAddress() );
		$donorAddress->setCountryCode( $updateDonorRequest->getCountryCode() );
		$donorAddress->assertNoNullFields();
		$donorAddress->freeze();
		return $donorAddress;
	}

	private function getDonorNameFromRequest( UpdateDonorRequest $updateDonorRequest ): DonorName {
		if ( $updateDonorRequest->getDonorType() === DonorName::PERSON_PRIVATE ) {
			$donorName = DonorName::newPrivatePersonName();
			$donorName->setFirstName( $updateDonorRequest->getFirstName() );
			$donorName->setLastName( $updateDonorRequest->getLastName() );
			$donorName->setSalutation( $updateDonorRequest->getSalutation() );
			$donorName->setTitle( $updateDonorRequest->getTitle() );
		} elseif ( $updateDonorRequest->getDonorType() === DonorName::PERSON_PRIVATE ) {
			$donorName = DonorName::newCompanyName();
			$donorName->setCompanyName( $updateDonorRequest->getCompanyName() );
		} else {
			// This should only happen if the UpdateDonorValidator does not catch invalid address types
			throw new \UnexpectedValueException( 'Donor must be a known PersonType' );
		}
		$donorName->assertNoNullFields();
		$donorName->freeze();
		return $donorName;
	}

	private function requestIsAllowed( UpdateDonorRequest $updateDonorRequest ): bool {
		return $this->authorizationService->userCanModifyDonation( $updateDonorRequest->getDonationId() );
	}
}