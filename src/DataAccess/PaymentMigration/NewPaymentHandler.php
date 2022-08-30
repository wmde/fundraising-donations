<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

interface NewPaymentHandler {
	public function handlePayment( Payment $payment, int $donationId ): void;
}
