<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\CancelPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\FailureResponse;

class CancelDonationUseCase {

	private const LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN = 'cancelled by user: %s';

	public function __construct(
		private readonly DonationRepository $donationRepository,
		private readonly DonationAuthorizationChecker $authorizationService,
		private readonly DonationEventLogger $donationLogger,
		private readonly CancelPaymentUseCase $cancelPaymentUseCase
	) {
	}

	public function cancelDonation( CancelDonationRequest $cancellationRequest ): CancelDonationResponse {
		if ( !$this->requestIsAllowedToModifyDonation( $cancellationRequest ) ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		try {
			$donation = $this->donationRepository->getDonationById( $cancellationRequest->getDonationId() );
		} catch ( GetDonationException $ex ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		if ( $donation === null ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		$cancelPaymentResponse = $this->cancelPaymentUseCase->cancelPayment( $donation->getPaymentId() );
		if ( $cancelPaymentResponse instanceof FailureResponse ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		$donation->cancel();

		try {
			$this->donationRepository->storeDonation( $donation );
		} catch ( StoreDonationException $ex ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		$this->donationLogger->log( $donation->getId(), $this->getLogMessage( $cancellationRequest ) );

		return $this->newSuccessResponse( $cancellationRequest );
	}

	public function getLogMessage( CancelDonationRequest $cancellationRequest ): string {
		return sprintf( self::LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN, $cancellationRequest->getUserName() );
	}

	public function requestIsAllowedToModifyDonation( CancelDonationRequest $cancellationRequest ): bool {
		if ( $cancellationRequest->isAuthorizedRequest() ) {
			return $this->authorizationService->systemCanModifyDonation( $cancellationRequest->getDonationId() );

		}
		// Users on the frontend are no longer allowed to cancel donations
		return false;
	}

	private function newFailureResponse( CancelDonationRequest $cancellationRequest ): CancelDonationResponse {
		return new CancelDonationResponse(
			$cancellationRequest->getDonationId(),
			CancelDonationResponse::FAILURE
		);
	}

	private function newSuccessResponse( CancelDonationRequest $cancellationRequest ): CancelDonationResponse {
		return new CancelDonationResponse(
			$cancellationRequest->getDonationId(),
			CancelDonationResponse::SUCCESS
		);
	}

}
