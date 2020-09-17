<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;

/**
 * This use case is for adding donor information to a donation after it was created.
 *
 * This allows for "quick" anonymous donations that the donor updates later in the funnel.
 *
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

		if ( $donation->getDonor()->getPhysicalAddress() instanceof PostalAddress ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_DONATION_HAS_ADDRESS );
		}

		$validationResult = $this->updateDonorValidator->validateDonorData( $updateDonorRequest );
		if ( $validationResult->getViolations() ) {
			// We don't need to return the full validation result since we rely on the client-side validation to catch
			// invalid input and don't output individual field violations in the PHP template
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_VALIDATION_FAILED, $donation );
		}

		$donation->setDonor( $this->getDonorFromRequest( $updateDonorRequest ) );
		$this->donationRepository->storeDonation( $donation );

		$this->donationConfirmationMailer->sendConfirmationMailFor( $donation );

		return UpdateDonorResponse::newSuccessResponse( UpdateDonorResponse::SUCCESS_TEXT, $donation );
	}

	private function getDonorAddressFromRequest( UpdateDonorRequest $updateDonorRequest ): PostalAddress {
		return new PostalAddress(
			$updateDonorRequest->getStreetAddress(),
			$updateDonorRequest->getPostalCode(),
			$updateDonorRequest->getCity(),
			$updateDonorRequest->getCountryCode()
		);
	}

	private function getDonorFromRequest( UpdateDonorRequest $updateDonorRequest ): Donor {
		if ( $updateDonorRequest->getDonorType() === UpdateDonorRequest::TYPE_PERSON ) {
			return new PersonDonor(
				new PersonName(
					$updateDonorRequest->getFirstName(),
					$updateDonorRequest->getLastName(),
					$updateDonorRequest->getSalutation(),
					$updateDonorRequest->getTitle()
				),
				$this->getDonorAddressFromRequest( $updateDonorRequest ),
				$updateDonorRequest->getEmailAddress()
			);

		} elseif ( $updateDonorRequest->getDonorType() === UpdateDonorRequest::TYPE_COMPANY ) {
			return new CompanyDonor(
				new CompanyName( $updateDonorRequest->getCompanyName() ),
				$this->getDonorAddressFromRequest( $updateDonorRequest ),
				$updateDonorRequest->getEmailAddress()
			);
		}

		// This should only happen if the UpdateDonorValidator does not catch invalid address types
		throw new \UnexpectedValueException( 'Donor must be a company or person' );
	}

	private function requestIsAllowed( UpdateDonorRequest $updateDonorRequest ): bool {
		return $this->authorizationService->userCanModifyDonation( $updateDonorRequest->getDonationId() );
	}
}
