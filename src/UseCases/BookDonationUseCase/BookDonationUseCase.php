<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\BookDonationUseCase;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Services\PaymentBookingService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FollowUpSuccessResponse;

class BookDonationUseCase {

	public function __construct(
		private readonly DonationIdRepository $idGenerator,
		private readonly DonationRepository $repository,
		private readonly DonationAuthorizationChecker $authorizationService,
		private readonly DonationNotifier $notifier,
		private readonly PaymentBookingService $paymentBookingService,
		private readonly DonationEventLogger $eventLogger
	) {
	}

	public function handleNotification( NotificationRequest $request ): NotificationResponse {
		$donation = $this->repository->getDonationById( $request->donationId );

		if ( $donation === null ) {
			return NotificationResponse::newDonationNotFoundResponse();
		}

		return $this->handleRequestForDonation( $request, $donation );
	}

	private function handleRequestForDonation( NotificationRequest $request, Donation $donation ): NotificationResponse {
		$donationId = $donation->getId();
		$isFollowupPayment = false;

		if ( !$this->authorizationService->systemCanModifyDonation( $donationId ) ) {
			return NotificationResponse::newFailureResponse( 'Wrong access code for donation' );
		}

		$result = $this->paymentBookingService->bookPayment( $donation->getPaymentId(), $request->bookingData );
		if ( $result instanceof FailureResponse ) {
			return $this->createFailureResponseFromPaymentServiceResponse( $result );
		}
		if ( $result instanceof FollowUpSuccessResponse ) {
			$donation = $donation->createFollowupDonationForPayment( $this->idGenerator->getNewId(), $result->childPaymentId );
			$isFollowupPayment = true;
		}
		$donation->confirmBooked();

		$this->repository->storeDonation( $donation );
		$this->eventLogger->log( $donation->getId(), 'book_donation_use_case: booked' );

		if ( $isFollowupPayment ) {
			$this->logChildDonationCreatedEvent( intval( $donationId ), intval( $donation->getId() ) );
		} else {
			$this->notifier->sendConfirmationFor( $donation );
		}

		return NotificationResponse::newSuccessResponse();
	}

	private function logChildDonationCreatedEvent( int $parentId, int $followUpId ): void {
		if ( $parentId == $followUpId ) {
			return;
		}
		$this->eventLogger->log(
			$parentId,
			"book_donation_use_case: new transaction id to corresponding child donation: $followUpId"
		);
		$this->eventLogger->log(
			$followUpId,
			"book_donation_use_case: new transaction id to corresponding parent donation: $parentId"
		);
	}

	private function createFailureResponseFromPaymentServiceResponse( FailureResponse $result ): NotificationResponse {
		if ( $result->paymentWasAlreadyCompleted() ) {
			return NotificationResponse::newAlreadyCompletedResponse();
		}
		return NotificationResponse::newFailureResponse( $result->message );
	}

}
