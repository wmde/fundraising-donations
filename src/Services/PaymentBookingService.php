<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Services;

use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\SuccessResponse;

interface PaymentBookingService {

	/**
	 * @param int $paymentId
	 * @param array<string,scalar> $transactionData
	 *
	 * @return FailureResponse|SuccessResponse
	 */
	public function bookPayment( int $paymentId, array $transactionData ): FailureResponse|SuccessResponse;
}
