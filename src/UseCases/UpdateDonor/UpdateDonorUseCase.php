<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Event\DonorUpdatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;

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
	private DonationNotifier $donationConfirmationMailer;
	private EventEmitter $eventEmitter;

	public function __construct(
		DonationAuthorizer $authorizationService,
		UpdateDonorValidator $updateDonorValidator,
		DonationRepository $donationRepository,
		DonationNotifier $donationConfirmationMailer,
		EventEmitter $eventEmitter
	) {
		$this->authorizationService = $authorizationService;
		$this->donationRepository = $donationRepository;
		$this->updateDonorValidator = $updateDonorValidator;
		$this->donationConfirmationMailer = $donationConfirmationMailer;
		$this->eventEmitter = $eventEmitter;
	}

	public function updateDonor( UpdateDonorRequest $updateDonorRequest ): UpdateDonorResponse {
		if ( !$this->requestIsAllowed( $updateDonorRequest ) ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_ACCESS_DENIED );
		}

		// No null check needed here, because authorizationService will deny access to non-existing donations
		$donation = $this->donationRepository->getDonationById( $updateDonorRequest->getDonationId() );

		if ( $donation->isCancelled() ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_ACCESS_DENIED );
		}

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

		$previousDonor = $donation->getDonor();
		$newDonor = $this->getDonorFromRequest( $updateDonorRequest );

		$donation->setDonor( $newDonor );
		$this->donationRepository->storeDonation( $donation );

		$this->eventEmitter->emit( new DonorUpdatedEvent( $donation->getId(), $previousDonor, $newDonor ) );
		$this->donationConfirmationMailer->sendConfirmationFor( $donation );

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
		if ( $updateDonorRequest->getDonorType()->is( DonorType::PERSON() ) ) {
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

		} elseif ( $updateDonorRequest->getDonorType()->is( DonorType::COMPANY() ) ) {
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
