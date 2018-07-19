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
			return UpdateDonorResponse::newFailureResponse( 'donor_change_failure_access_denied' );
		}
		$donation = $this->donationRepository->getDonationById( $updateDonorRequest->getDonationId() );
		if ( $donation->isExported() ) {
			return UpdateDonorResponse::newFailureResponse( 'donor_change_failure_exported' );
		}
		$validationResult = $this->updateDonorValidator->validateDonorData( $updateDonorRequest );
		if ( $validationResult->getViolations() ) {
			return UpdateDonorResponse::newFailureResponse( $validationResult->getFirstViolation() );
		}
		try {
			$donor = new Donor(
				$this->getDonorNameFromRequest( $updateDonorRequest ),
				$this->getDonorAddressFromRequest( $updateDonorRequest ),
				$updateDonorRequest->getEmailAddress()
			);
		}
		catch ( \UnexpectedValueException $e ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::VIOLATION_GENERIC );
		}

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