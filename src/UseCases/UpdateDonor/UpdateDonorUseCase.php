<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonorAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;

/**
 * @license GPL-2.0-or-later
 */
class UpdateDonorUseCase {

	private DonationAuthorizer $authorizationService;
	private DonationRepository $donationRepository;
	private UpdateDonorValidator $updateDonorValidator;
	private DonationConfirmationMailer $donationConfirmationMailer;

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
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_VALIDATION_FAILED, $donation );
		}

		$donor = new LegacyDonor(
			$this->getDonorNameFromRequest( $updateDonorRequest ),
			$this->getDonorAddressFromRequest( $updateDonorRequest ),
			$updateDonorRequest->getEmailAddress()
		);

		$donation->setDonor( $donor );
		$this->donationRepository->storeDonation( $donation );

		$this->donationConfirmationMailer->sendConfirmationMailFor( $donation );

		return UpdateDonorResponse::newSuccessResponse( UpdateDonorResponse::SUCCESS_TEXT, $donation );
	}

	private function getDonorAddressFromRequest( UpdateDonorRequest $updateDonorRequest ): LegacyDonorAddress {
		return new LegacyDonorAddress(
			$updateDonorRequest->getStreetAddress(),
			$updateDonorRequest->getPostalCode(),
			$updateDonorRequest->getCity(),
			$updateDonorRequest->getCountryCode()
		);
	}

	private function getDonorNameFromRequest( UpdateDonorRequest $updateDonorRequest ): DonorName {
		if ( $updateDonorRequest->getDonorType() === UpdateDonorRequest::TYPE_PERSON ) {
			return new PersonName(
				$updateDonorRequest->getFirstName(),
				$updateDonorRequest->getLastName(),
				$updateDonorRequest->getSalutation(),
				$updateDonorRequest->getTitle()
			);
		} elseif ( $updateDonorRequest->getDonorType() === UpdateDonorRequest::TYPE_ANONYMOUS ) {
			return new CompanyName( $updateDonorRequest->getCompanyName() );
		}

		// This should only happen if the UpdateDonorValidator does not catch invalid address types
		throw new \UnexpectedValueException( 'Donor must be a known PersonType' );
	}

	private function requestIsAllowed( UpdateDonorRequest $updateDonorRequest ): bool {
		return $this->authorizationService->userCanModifyDonation( $updateDonorRequest->getDonationId() );
	}
}
