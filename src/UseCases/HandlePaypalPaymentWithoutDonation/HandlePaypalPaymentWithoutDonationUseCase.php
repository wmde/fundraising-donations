<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\HandlePaypalPaymentWithoutDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Services\PaypalBookingService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\FailureResponse;

class HandlePaypalPaymentWithoutDonationUseCase {

	public function __construct(
		private readonly PaypalBookingService $paypalBookingService,
		private readonly DonationRepository $donationRepository,
		private DonationNotifier $notifier,
		private DonationEventLogger $eventLogger,
	) {
	}

	/**
	 * @param int $amountInCents
	 * @param array<string,mixed> $bookingData
	 *
	 * @return NotificationResponse
	 */
	public function handleNotification( int $amountInCents, array $bookingData ): NotificationResponse {
		$result = $this->paypalBookingService->bookNewPayment( $amountInCents, $bookingData );

		if ( $result instanceof FailureResponse ) {
			return NotificationResponse::newFailureResponse( $result->message );
		}

		$donation = new Donation(
			null,
			new AnonymousDonor(),
			$result->paymentId,
			Donation::DOES_NOT_OPT_INTO_NEWSLETTER,
			DonationTrackingInfo::newBlankTrackingInfo(),
		);

		$donation->setOptsIntoDonationReceipt( false );

		$this->donationRepository->storeDonation( $donation );

		$this->notifier->sendConfirmationFor( $donation );

		$this->eventLogger->log( $donation->getId(), 'handle_paypal_payment_without_donation_use_case: booked' );

		return NotificationResponse::newSuccessResponse();
	}
}
