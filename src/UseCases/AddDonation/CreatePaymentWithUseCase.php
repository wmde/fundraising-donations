<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\CreatePaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse as PaymentCreationFailed;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\SuccessResponse as PaymentCreationSucceeded;

class CreatePaymentWithUseCase implements CreatePaymentService {

	public function __construct( private CreatePaymentUseCase $createPaymentUseCase ) {
	}

	public function createPayment( PaymentCreationRequest $request ): PaymentCreationFailed|PaymentCreationSucceeded {
		return $this->createPaymentUseCase->createPayment( $request );
	}

}
