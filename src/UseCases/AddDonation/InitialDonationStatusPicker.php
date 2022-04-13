<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

class InitialDonationStatusPicker {

	public function __invoke( Payment $payment ): string {
		return match( true ) {
			$payment instanceof DirectDebitPayment => Donation::STATUS_NEW,
			$payment instanceof BankTransferPayment => Donation::STATUS_PROMISE,
			default => Donation::STATUS_EXTERNAL_INCOMPLETE
		};
	}
}
