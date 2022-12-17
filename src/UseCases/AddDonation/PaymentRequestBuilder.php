<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;

class PaymentRequestBuilder {
	private PaymentCreationRequest $paymentCreationRequest;

	public function __construct() {
		$this->paymentCreationRequest = new PaymentCreationRequest(
			0,
			PaymentInterval::OneTime->value,
			'',
			transferCodePrefix: ''
		);
	}

	public static function fromExistingRequest( PaymentCreationRequest $request ): self {
		$builder = new self();
		$builder->paymentCreationRequest = $request;
		return $builder;
	}

	public function getPaymentCreationRequest(): PaymentCreationRequest {
		return $this->paymentCreationRequest;
	}

	public function build(): PaymentCreationRequest {
		return $this->paymentCreationRequest;
	}

	public function withAmount( int $amount ): self {
		$this->paymentCreationRequest = new PaymentCreationRequest(
			$amount,
			$this->paymentCreationRequest->interval,
			$this->paymentCreationRequest->paymentType,
			$this->paymentCreationRequest->iban,
			$this->paymentCreationRequest->bic,
			$this->paymentCreationRequest->transferCodePrefix,
		);
		return $this;
	}

	public function withInterval( int $interval ): self {
		$this->paymentCreationRequest = new PaymentCreationRequest(
			$this->paymentCreationRequest->amountInEuroCents,
			$interval,
			$this->paymentCreationRequest->paymentType,
			$this->paymentCreationRequest->iban,
			$this->paymentCreationRequest->bic,
			$this->paymentCreationRequest->transferCodePrefix,
		);
		return $this;
	}

	public function withPaymentType( string $paymentType ): self {
		$this->paymentCreationRequest = new PaymentCreationRequest(
			$this->paymentCreationRequest->amountInEuroCents,
			$this->paymentCreationRequest->interval,
			$paymentType,
			$this->paymentCreationRequest->iban,
			$this->paymentCreationRequest->bic,
			$this->paymentCreationRequest->transferCodePrefix
		);
		return $this;
	}

	public function withBankData( string $iban, string $bic ): self {
		$this->paymentCreationRequest = new PaymentCreationRequest(
			$this->paymentCreationRequest->amountInEuroCents,
			$this->paymentCreationRequest->interval,
			$this->paymentCreationRequest->paymentType,
			$iban,
			$bic,
			$this->paymentCreationRequest->transferCodePrefix,
		);
		return $this;
	}

	public function withPaymentReferenceCode( string $paymentReferenceCode ): self {
		$this->paymentCreationRequest = new PaymentCreationRequest(
			$this->paymentCreationRequest->amountInEuroCents,
			$this->paymentCreationRequest->interval,
			$this->paymentCreationRequest->paymentType,
			$this->paymentCreationRequest->iban,
			$this->paymentCreationRequest->bic,
			$paymentReferenceCode
		);
		return $this;
	}
}
