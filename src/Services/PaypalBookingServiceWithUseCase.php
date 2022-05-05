<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Services;

use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\CreateBookedPayPalPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\SuccessResponse;

class PaypalBookingServiceWithUseCase implements PaypalBookingService {

	private CreateBookedPayPalPaymentUseCase $useCase;

	public function __construct( CreateBookedPayPalPaymentUseCase $bookedPayPalPaymentUseCase ) {
		$this->useCase = $bookedPayPalPaymentUseCase;
	}

	public function bookNewPayment( int $amountInCents, array $transactionData ): SuccessResponse|FailureResponse {
		return $this->useCase->bookNewPayment( $amountInCents, $transactionData );
	}
}
