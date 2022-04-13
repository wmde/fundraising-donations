<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext;

use WMDE\Euro\Euro;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;

class DummyPayment {

	public static function create(): Payment {
		trigger_error( "Created a placeholder payment, this should be replaced with a payment from the 'create payment' use case or the payment repository", E_USER_WARNING );
		return new CreditCardPayment(
			1,
			Euro::newFromString( '99.99' ),
			PaymentInterval::OneTime
		);
	}
}
