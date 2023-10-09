<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentParameters;

class PaymentParameterBuilder {
	private PaymentParameters $paymentParameters;

	public function __construct() {
		$this->paymentParameters = new PaymentParameters(
			0,
			PaymentInterval::OneTime->value,
			'',
			transferCodePrefix: ''
		);
	}

	public static function fromExistingParameters( PaymentParameters $request ): self {
		$builder = new self();
		$builder->paymentParameters = $request;
		return $builder;
	}

	public function getPaymentParameters(): PaymentParameters {
		return $this->paymentParameters;
	}

	public function build(): PaymentParameters {
		return $this->paymentParameters;
	}

	public function withAmount( int $amount ): self {
		$this->paymentParameters = new PaymentParameters(
			$amount,
			$this->paymentParameters->interval,
			$this->paymentParameters->paymentType,
			$this->paymentParameters->iban,
			$this->paymentParameters->bic,
			$this->paymentParameters->transferCodePrefix,
		);
		return $this;
	}

	public function withInterval( int $interval ): self {
		$this->paymentParameters = new PaymentParameters(
			$this->paymentParameters->amountInEuroCents,
			$interval,
			$this->paymentParameters->paymentType,
			$this->paymentParameters->iban,
			$this->paymentParameters->bic,
			$this->paymentParameters->transferCodePrefix,
		);
		return $this;
	}

	public function withPaymentType( string $paymentType ): self {
		$this->paymentParameters = new PaymentParameters(
			$this->paymentParameters->amountInEuroCents,
			$this->paymentParameters->interval,
			$paymentType,
			$this->paymentParameters->iban,
			$this->paymentParameters->bic,
			$this->paymentParameters->transferCodePrefix
		);
		return $this;
	}

	public function withBankData( string $iban, string $bic ): self {
		$this->paymentParameters = new PaymentParameters(
			$this->paymentParameters->amountInEuroCents,
			$this->paymentParameters->interval,
			$this->paymentParameters->paymentType,
			$iban,
			$bic,
			$this->paymentParameters->transferCodePrefix,
		);
		return $this;
	}

	public function withPaymentReferenceCodePrefix( string $transferCodePrefix ): self {
		$this->paymentParameters = new PaymentParameters(
			$this->paymentParameters->amountInEuroCents,
			$this->paymentParameters->interval,
			$this->paymentParameters->paymentType,
			$this->paymentParameters->iban,
			$this->paymentParameters->bic,
			$transferCodePrefix
		);
		return $this;
	}
}
