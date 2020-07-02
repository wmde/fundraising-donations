<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

use WMDE\Euro\Euro;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DonationPayment {

	private $amount;
	private $intervalInMonths;
	private $paymentMethod;

	public function __construct( Euro $amount, int $intervalInMonths, PaymentMethod $paymentMethod ) {
		$this->amount = $amount;
		$this->intervalInMonths = $intervalInMonths;
		$this->paymentMethod = $paymentMethod;
	}

	public function getAmount(): Euro {
		return $this->amount;
	}

	public function getIntervalInMonths(): int {
		return $this->intervalInMonths;
	}

	public function getPaymentMethod(): PaymentMethod {
		return $this->paymentMethod;
	}

}
