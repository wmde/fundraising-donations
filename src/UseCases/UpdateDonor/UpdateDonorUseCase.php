<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Event\DonorUpdatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;

/**
 * This use case is for adding donor information to a donation after it was created.
 *
 * This allows for "quick" anonymous donations that the donor updates later in the funnel.
 */
class UpdateDonorUseCase {

	private DonationAuthorizationChecker $authorizationService;
	private DonationRepository $donationRepository;
	private UpdateDonorValidator $updateDonorValidator;
	private DonationNotifier $donationConfirmationMailer;
	private EventEmitter $eventEmitter;

	public function __construct(
		DonationAuthorizationChecker $authorizationService,
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

		$donation = $this->donationRepository->getDonationById( $updateDonorRequest->getDonationId() );

		if ( $donation === null ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_DONATION_NOT_FOUND );
		}

		if ( $donation->isCancelled() ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_ACCESS_DENIED );
		}

		if ( $donation->isExported() ) {
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_DONATION_IS_EXPORTED );
		}

		$validationResult = $this->updateDonorValidator->validateDonorData( $updateDonorRequest );
		if ( $validationResult->getViolations() ) {
			// We don't need to return the full validation result since we rely on the client-side validation to catch
			// invalid input and don't output individual field violations in the PHP template
			return UpdateDonorResponse::newFailureResponse( UpdateDonorResponse::ERROR_VALIDATION_FAILED, $donation );
		}

		$previousDonor = $donation->getDonor();
		$newDonor = $this->getDonorFromRequest( $updateDonorRequest );
		$this->updateMailingListSubscription( $updateDonorRequest, $newDonor );

		$donation->setDonor( $newDonor );
		$this->donationRepository->storeDonation( $donation );

		$this->eventEmitter->emit( new DonorUpdatedEvent( intval( $donation->getId() ), $previousDonor, $newDonor ) );
		$this->donationConfirmationMailer->sendConfirmationFor( $donation );

		return UpdateDonorResponse::newSuccessResponse( UpdateDonorResponse::SUCCESS_TEXT, $donation );
	}

	private function getDonorAddressFromRequest( UpdateDonorRequest $updateDonorRequest ): PostalAddress {
		if ( trim( $updateDonorRequest->getStreetName() ) !== '' && trim( $updateDonorRequest->getHouseNumber() ) !== '' ) {
			return PostalAddress::fromStreetNameAndHouseNumber(
				$updateDonorRequest->getStreetName(),
				$updateDonorRequest->getHouseNumber(),
				$updateDonorRequest->getPostalCode(),
				$updateDonorRequest->getCity(),
				$updateDonorRequest->getCountryCode()
			);
		} else {
			return PostalAddress::fromLegacyStreetName(
				$updateDonorRequest->getStreetAddress(),
				$updateDonorRequest->getPostalCode(),
				$updateDonorRequest->getCity(),
				$updateDonorRequest->getCountryCode()
			);
		}
	}

	private function getDonorFromRequest( UpdateDonorRequest $updateDonorRequest ): Donor {
		if ( $updateDonorRequest->getDonorType() === DonorType::PERSON ) {
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

		} elseif ( $updateDonorRequest->getDonorType() === DonorType::COMPANY ) {
			return new CompanyDonor(
				new CompanyContactName(
					$updateDonorRequest->getCompanyName(),
					$updateDonorRequest->getFirstName(),
					$updateDonorRequest->getLastName(),
					$updateDonorRequest->getSalutation(),
					$updateDonorRequest->getTitle()
				),
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

	public function updateMailingListSubscription( UpdateDonorRequest $updateDonorRequest, Donor $newDonor ): void {
		if ( $updateDonorRequest->getMailingList() ) {
			$newDonor->subscribeToMailingList();
		} else {
			$newDonor->unsubscribeFromMailingList();
		}
	}
}
