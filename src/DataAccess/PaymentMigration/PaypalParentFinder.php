<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;

interface PaypalParentFinder {

	/**
	 * @param array<string,mixed> $row
	 * @param ConversionResult $result
	 *
	 * @return PayPalPayment|null
	 */
	public function getParentPaypalPayment( array $row, ConversionResult $result ): ?PayPalPayment;
}
