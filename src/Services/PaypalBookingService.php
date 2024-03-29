<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Services;

use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\SuccessResponse;

interface PaypalBookingService {
	/**
	 * @param int $amountInCents
	 * @param array<string,mixed> $transactionData
	 *
	 * @return SuccessResponse|FailureResponse
	 */
	public function bookNewPayment( int $amountInCents, array $transactionData ): SuccessResponse|FailureResponse;
}
