<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Services;

use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\SuccessResponse;

interface PaymentBookingService {

	public function bookPayment( int $paymentId, array $transactionData ): FailureResponse|SuccessResponse;
}
