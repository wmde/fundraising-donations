<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\CreatePaymentService;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\DonationPaymentValidator;
use WMDE\Fundraising\PaymentContext\Domain\PaymentType;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\SuccessResponse as PaymentCreationSucceeded;

class SucceedingPaymentServiceStub implements CreatePaymentService {

	private PaymentCreationSucceeded $successResponse;

	public function __construct( ?PaymentCreationSucceeded $successResponse = null ) {
		$this->successResponse = $successResponse ?? new PaymentCreationSucceeded(
				ValidPayments::ID_BANK_TRANSFER,
				'',
				true
			);
	}

	public function createPayment( PaymentCreationRequest $request ): PaymentCreationSucceeded {
		return $this->successResponse;
	}

	public function createPaymentValidator(): DonationPaymentValidator {
		return new DonationPaymentValidator( PaymentType::cases() );
	}

}
