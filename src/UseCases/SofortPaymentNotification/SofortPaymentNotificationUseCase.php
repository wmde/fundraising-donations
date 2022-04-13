<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\SofortPaymentNotification;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;

class SofortPaymentNotificationUseCase {

	private DonationRepository $repository;
	private DonationAuthorizer $authorizationService;
	private DonationConfirmationNotifier $notifier;

	public function __construct( DonationRepository $repository, DonationAuthorizer $authorizationService,
		DonationConfirmationNotifier $notifier ) {
		$this->repository = $repository;
		$this->authorizationService = $authorizationService;
		$this->notifier = $notifier;
	}

	public function handleNotification( NotificationRequest $request ): NotificationResponse {
		// TODO Consolidate booking use cases
		return new NotificationResponse();
		/*
		try {
			$donation = $this->repository->getDonationById( $request->donationId );
		}
		catch ( GetDonationException $ex ) {
			return $this->createFailureResponse( $ex );
		}

		if ( $donation === null ) {
			return $this->createFailureResponse( new RuntimeException( 'Donation not found' ) );
		}

		return $this->handleRequestForDonation( $request, $donation );
		*/
	}

/*
	private function handleRequestForDonation( NotificationRequest $request, Donation $donation ): NotificationResponse {
		$paymentMethod = $donation->getPayment()->getPaymentMethod();

		if ( !( $paymentMethod instanceof SofortPayment ) ) {
			return $this->createUnhandledResponse( 'Trying to handle notification for non-sofort donation' );
		}

		if ( !$this->authorizationService->systemCanModifyDonation( $donation->getId() ) ) {
			return $this->createUnhandledResponse( 'Wrong access code for donation' );
		}

		if ( $paymentMethod->paymentCompleted() ) {
			return $this->createUnhandledResponse( 'Duplicate notification' );
		}

		try {
			$donation->confirmBooked( $request->bookingData );
		}
		catch ( DomainException $ex ) {
			return $this->createFailureResponse( $ex );
		}

		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return $this->createFailureResponse( $ex );
		}

		$this->notifier->sendConfirmationFor( $donation );

		return NotificationResponse::newSuccessResponse();
	}

	private function createUnhandledResponse( string $reason ): NotificationResponse {
		return NotificationResponse::newUnhandledResponse(
			[
				'message' => $reason
			]
		);
	}

	private function createFailureResponse( Exception $ex ): NotificationResponse {
		return NotificationResponse::newFailureResponse(
			[
				'message' => $ex->getMessage(),
				'stackTrace' => $ex->getTraceAsString()
			]
		);
	}
*/
}
