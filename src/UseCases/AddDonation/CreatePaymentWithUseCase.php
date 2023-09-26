<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\PaymentContext\Domain\PaymentType;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\CreatePaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse as PaymentCreationFailed;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\SuccessResponse as PaymentCreationSucceeded;

class CreatePaymentWithUseCase implements CreatePaymentService {

	/**
	 * @param CreatePaymentUseCase $createPaymentUseCase
	 * @param PaymentType[] $allowedPaymentTypes
	 */
	public function __construct(
		private CreatePaymentUseCase $createPaymentUseCase,
		private array $allowedPaymentTypes
	) {
	}

	public function createPayment( PaymentCreationRequest $request ): PaymentCreationFailed|PaymentCreationSucceeded {
		return $this->createPaymentUseCase->createPayment( $request );
	}

	public function createPaymentValidator(): DonationPaymentValidator {
		return new DonationPaymentValidator( $this->allowedPaymentTypes );
	}

}
