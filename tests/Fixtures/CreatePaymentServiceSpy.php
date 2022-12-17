<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\UseCases\AddDonation\DonationPaymentValidator;
use WMDE\Fundraising\PaymentContext\Domain\PaymentType;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\SuccessResponse as PaymentCreationSucceeded;

class CreatePaymentServiceSpy extends SucceedingPaymentServiceStub {
	/**
	 * @var PaymentCreationRequest[]
	 */
	private array $requests = [];

	public function createPayment( PaymentCreationRequest $request ): PaymentCreationSucceeded {
		$this->requests[] = $request;
		return parent::createPayment( $request );
	}

	public function createPaymentValidator(): DonationPaymentValidator {
		return new DonationPaymentValidator( PaymentType::cases() );
	}

	public function getLastRequest(): PaymentCreationRequest {
		return $this->requests[0];
	}

}
