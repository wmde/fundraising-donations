<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\BookDonationUseCase;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Services\PaymentBookingService;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;

class BookDonationUseCase {

	private DonationRepository $repository;
	private DonationAuthorizer $authorizationService;
	private DonationConfirmationNotifier $notifier;
	private PaymentBookingService $paymentBookingService;

	public function __construct( DonationRepository $repository, DonationAuthorizer $authorizationService,
		DonationConfirmationNotifier $notifier, PaymentBookingService $paymentBookingService ) {
		$this->repository = $repository;
		$this->authorizationService = $authorizationService;
		$this->notifier = $notifier;
		$this->paymentBookingService = $paymentBookingService;
	}

	public function handleNotification( NotificationRequest $request ): NotificationResponse {
		$donation = $this->repository->getDonationById( $request->donationId );

		if ( $donation === null ) {
			return NotificationResponse::newFailureResponse( 'Donation not found' );
		}

		return $this->handleRequestForDonation( $request, $donation );
	}

	private function handleRequestForDonation( NotificationRequest $request, Donation $donation ): NotificationResponse {
		if ( !$this->authorizationService->systemCanModifyDonation( $donation->getId() ) ) {
			return NotificationResponse::newUnhandledResponse( 'Wrong access code for donation' );
		}

		$result = $this->paymentBookingService->bookPayment( $donation->getPaymentId(), $request->bookingData );
		if ( $result instanceof FailureResponse ) {
			return NotificationResponse::newUnhandledResponse( $result->message );
		}
		$donation->confirmBooked();

		$this->repository->storeDonation( $donation );

		$this->notifier->sendConfirmationFor( $donation );

		return NotificationResponse::newSuccessResponse();
	}

}
