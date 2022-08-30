<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;

interface PaypalParentFinder {
	public function getParentPaypalPayment( array $row, ConversionResult $result ): ?PayPalPayment;
}
