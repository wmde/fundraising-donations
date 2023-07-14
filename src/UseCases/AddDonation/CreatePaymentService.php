<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\DomainSpecificPaymentCreationRequest;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse as PaymentCreationFailed;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\SuccessResponse as PaymentCreationSucceeded;

interface CreatePaymentService {
	public function createPayment( DomainSpecificPaymentCreationRequest $request ): PaymentCreationFailed|PaymentCreationSucceeded;

	public function createPaymentValidator(): DonationPaymentValidator;
}
