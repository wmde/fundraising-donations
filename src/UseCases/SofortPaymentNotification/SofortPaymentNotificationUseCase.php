<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\SofortPaymentNotification;

use RuntimeException;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\RequestModel\SofortNotificationRequest;
use WMDE\Fundraising\PaymentContext\ResponseModel\SofortNotificationResponse;

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

	public function handleNotification( SofortNotificationRequest $request ): SofortNotificationResponse {
		try {
			$donation = $this->repository->getDonationById( $request->getDonationId() );
		}
		catch ( GetDonationException $ex ) {
			return $this->createFailureResponse( $ex );
		}

		if ( $donation === null ) {
			return $this->createFailureResponse( new RuntimeException( 'Donation not found' ) );
		}

		return $this->handleRequestForDonation( $request, $donation );
	}

	private function handleRequestForDonation( SofortNotificationRequest $request, Donation $donation ): SofortNotificationResponse {
		$paymentMethod = $donation->getPayment()->getPaymentMethod();

		if ( !( $paymentMethod instanceof SofortPayment ) ) {
			return $this->createUnhandledResponse( 'Trying to handle notification for non-sofort donation' );
		}

		if ( !$this->authorizationService->systemCanModifyDonation( $donation->getId() ) ) {
			return $this->createUnhandledResponse( 'Wrong access code for donation' );
		}

		if ( $paymentMethod->isConfirmedPayment() ) {
			return $this->createUnhandledResponse( 'Duplicate notification' );
		}

		try {
			$donation->confirmBooked();
		}
		catch ( \RuntimeException $ex ) {
			return $this->createFailureResponse( $ex );
		}

		$paymentMethod->setConfirmedAt( $request->getTime() );

		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return $this->createFailureResponse( $ex );
		}

		$this->notifier->sendConfirmationFor( $donation );

		return SofortNotificationResponse::newSuccessResponse();
	}

	private function createUnhandledResponse( string $reason ): SofortNotificationResponse {
		return SofortNotificationResponse::newUnhandledResponse(
			[
				'message' => $reason
			]
		);
	}

	private function createFailureResponse( RuntimeException $ex ): SofortNotificationResponse {
		return SofortNotificationResponse::newFailureResponse(
			[
				'message' => $ex->getMessage(),
				'stackTrace' => $ex->getTraceAsString()
			]
		);
	}

}
