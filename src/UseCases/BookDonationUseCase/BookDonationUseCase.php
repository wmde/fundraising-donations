<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\BookDonationUseCase;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Services\PaymentBookingService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FollowUpSuccessResponse;

class BookDonationUseCase {

	private DonationRepository $repository;
	private DonationAuthorizer $authorizationService;
	private DonationNotifier $notifier;
	private PaymentBookingService $paymentBookingService;
	private DonationEventLogger $eventLogger;

	public function __construct( DonationRepository $repository, DonationAuthorizer $authorizationService,
								 DonationNotifier $notifier, PaymentBookingService $paymentBookingService,
								 DonationEventLogger $eventLogger ) {
		$this->repository = $repository;
		$this->authorizationService = $authorizationService;
		$this->notifier = $notifier;
		$this->paymentBookingService = $paymentBookingService;
		$this->eventLogger = $eventLogger;
	}

	public function handleNotification( NotificationRequest $request ): NotificationResponse {
		$donation = $this->repository->getDonationById( $request->donationId );

		if ( $donation === null ) {
			return NotificationResponse::newFailureResponse( 'Donation not found' );
		}

		return $this->handleRequestForDonation( $request, $donation );
	}

	private function handleRequestForDonation( NotificationRequest $request, Donation $donation ): NotificationResponse {
		$donationId = $donation->getId();
		$isFollowupPayment = false;

		if ( !$this->authorizationService->systemCanModifyDonation( $donationId ) ) {
			return NotificationResponse::newUnhandledResponse( 'Wrong access code for donation' );
		}

		$result = $this->paymentBookingService->bookPayment( $donation->getPaymentId(), $request->bookingData );
		if ( $result instanceof FailureResponse ) {
			return NotificationResponse::newUnhandledResponse( $result->message );
		}
		if ( $result instanceof FollowUpSuccessResponse ) {
			$donation = $donation->createFollowupDonationForPayment( $result->childPaymentId );
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

}
