<?php
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use DateTimeImmutable;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * This is a class that should trigger errors in our payment method instance checks
 */
class InvalidPaymentMethod implements PaymentMethod {
	public function getId(): string {
		return 'CASH';
	}

	public function hasExternalProvider(): bool {
		return false;
	}

	public function paymentCompleted(): bool {
		return true;
	}

	public function getValuationDate(): ?DateTimeImmutable {
		return null;
	}
}
