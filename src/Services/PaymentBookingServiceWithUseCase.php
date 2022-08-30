<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Services;

use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\BookPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\SuccessResponse;

class PaymentBookingServiceWithUseCase implements PaymentBookingService {

	public function __construct(
		private readonly BookPaymentUseCase $bookPaymentUseCase
	) {
	}

	public function bookPayment( int $paymentId, array $transactionData ): FailureResponse|SuccessResponse {
		return $this->bookPaymentUseCase->bookPayment( $paymentId, $transactionData );
	}

}
