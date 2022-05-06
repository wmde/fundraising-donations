<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use PHPUnit\Framework\Assert;
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

	public function getRequests(): array {
		return $this->requests;
	}

	public function getLastRequest(): PaymentCreationRequest {
		return $this->requests[0];
	}

	public function assertCalledOnce(): void {
		Assert::assertCount( 1, $this->requests );
	}

}
