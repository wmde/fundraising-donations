<?php
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use DateTimeImmutable;
use WMDE\Euro\Euro;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;

/**
 * This is a class that should trigger errors in our payment method instance checks
 */
class InvalidPayment extends Payment {

	public const PAYMENT_METHOD = 'CASH';

	public function __construct() {
		parent::__construct( 99, Euro::newFromCents( 1000 ), PaymentInterval::OneTime, self::PAYMENT_METHOD );
	}

	protected function getPaymentName(): string {
		return self::PAYMENT_METHOD;
	}

	protected function getPaymentSpecificLegacyData(): array {
		return [];
	}

	public function getValuationDate(): ?DateTimeImmutable {
		return null;
	}

	protected function getLegacyPaymentStatus(): string {
		return '';
	}
}
