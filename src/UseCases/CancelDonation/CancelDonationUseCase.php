<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\CancelPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\FailureResponse;

class CancelDonationUseCase {

	private const string LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN = 'cancelled by user: %s';

	public function __construct(
		private readonly DonationRepository $donationRepository,
		private readonly DonationEventLogger $donationLogger,
		private readonly CancelPaymentUseCase $cancelPaymentUseCase
	) {
	}

	public function cancelDonation( CancelDonationRequest $cancellationRequest ): CancelDonationSuccessResponse|CancelDonationFailureResponse {
		$donationId = $cancellationRequest->getDonationId();
		try {
			$donation = $this->donationRepository->getDonationById( $donationId );
		} catch ( GetDonationException $ex ) {
			return new CancelDonationFailureResponse( $donationId, $ex->getMessage() );
		}

		if ( $donation === null ) {
			return new CancelDonationFailureResponse( $donationId, 'Donation not found.' );
		}

		$cancelPaymentResponse = $this->cancelPaymentUseCase->cancelPayment( $donation->getPaymentId() );
		if ( $cancelPaymentResponse instanceof FailureResponse ) {
			return new CancelDonationFailureResponse( $donationId, $cancelPaymentResponse->message );
		}

		$donation->cancel();

		try {
			$this->donationRepository->storeDonation( $donation );
		} catch ( StoreDonationException $ex ) {
			return new CancelDonationFailureResponse( $donationId, $ex->getMessage() );
		}

		$this->donationLogger->log( $donation->getId(), $this->getLogMessage( $cancellationRequest ) );

		return new CancelDonationSuccessResponse( $donationId );
	}

	public function getLogMessage( CancelDonationRequest $cancellationRequest ): string {
		return sprintf( self::LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN, $cancellationRequest->getUserName() );
	}

}
