<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\RestoreDonation;

use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\CancelPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\FailureResponse;

class RestoreDonationUseCase {

	private const string LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN = 'restored by user: %s';

	public function __construct(
		private readonly DonationRepository $donationRepository,
		private readonly DonationEventLogger $donationLogger,
		private readonly CancelPaymentUseCase $cancelPaymentUseCase
	) {
	}

	public function restoreCancelledDonation( int $donationId, string $authorizedUser ): RestoreDonationSuccessResponse|RestoreDonationFailureResponse {
		$donation = $this->donationRepository->getDonationById( $donationId );
		if ( $donation === null ) {
			return new RestoreDonationFailureResponse( $donationId, RestoreDonationFailureResponse::DONATION_NOT_FOUND );
		}
		if ( !$donation->isCancelled() ) {
			return new RestoreDonationFailureResponse( $donationId, RestoreDonationFailureResponse::DONATION_NOT_CANCELED );
		}

		$restorePaymentResponse = $this->cancelPaymentUseCase->restorePayment( $donation->getPaymentId() );
		if ( $restorePaymentResponse instanceof FailureResponse ) {
			return new RestoreDonationFailureResponse( $donationId, $restorePaymentResponse->message );
		}

		$donation->revokeCancellation();
		$this->donationRepository->storeDonation( $donation );
		$this->donationLogger->log( $donationId, sprintf( self::LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN, $authorizedUser ) );

		return new RestoreDonationSuccessResponse( $donationId );
	}
}
