<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Euro\Euro;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentReferenceCode;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Domain\Repositories\PaymentIDRepository;

class ValidPayments {

	public const CREDIT_CARD_TRANSACTION_ID = '7788998877';

	public const PAYPAL_PAYER_ID = 'HE373U84ENFYQ';
	public const PAYMENT_VALUATION_DATE = '2021-05-15 21:12:00';
	public const PAYPAL_TRANSACTION_ID = '61E67681CH3238416';

	public const PAYMENT_BANK_ACCOUNT = '0648489890';
	public const PAYMENT_BANK_CODE = '50010517';
	public const PAYMENT_BANK_NAME = 'ING-DiBa';
	public const PAYMENT_BIC = 'INGDDEFFXXX';
	public const PAYMENT_IBAN = 'DE12500105170648489890';

	public const PAYMENT_BANK_TRANSFER_CODE = 'XW-DAR-E99-X';

	public const PAYMENT_SOFORT_VALUATION_DATE = '2021-06-15T05:07:00Z';
	public const SOFORT_TRANSACTION_ID = '44544554';
	public const SOFORT_DONATION_CONFIRMED_AT = '-1 hour';

	// Use fractional value to detect floating point issues
	public const DONATION_AMOUNT = 13.37;
	public const PAYMENT_INTERVAL_IN_MONTHS = 3;

	public static function newPayPalPayment(): PayPalPayment {
		return new PayPalPayment(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::Quarterly
		);
	}

	private static function newPaymentIdGeneratorStub() {
		return new class implements PaymentIDRepository {
			public function getNewID(): int {
				throw new \LogicException( 'Id generator should never be called - this is only for followup payments' );
			}

		};
	}

	public static function newBookedPayPalPayment( string $transactionId = self::PAYPAL_TRANSACTION_ID ): PayPalPayment {
		$payment = self::newPayPalPayment();
		$payment->bookPayment(
			[
				'payer_id' => self::PAYPAL_PAYER_ID,
				'txn_id' => $transactionId,
				'payment_date' => self::PAYMENT_VALUATION_DATE
			],
			self::newPaymentIdGeneratorStub()
		);
		return $payment;
	}

	public static function newDirectDebitPayment(): DirectDebitPayment {
		return DirectDebitPayment::create(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::Quarterly,
			new Iban( self::PAYMENT_IBAN ),
			self::PAYMENT_BIC
		);
	}

	public static function newSofortPayment(): SofortPayment {
		return SofortPayment::create(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::OneTime,
			PaymentReferenceCode::newFromString( self::PAYMENT_BANK_TRANSFER_CODE )
		);
	}

	public static function newCompletedSofortPayment(): SofortPayment {
		$payment = self::newSofortPayment();
		$payment->bookPayment(
			[ 'valuationDate' => self::PAYMENT_SOFORT_VALUATION_DATE, 'transactionId' => self::SOFORT_TRANSACTION_ID ],
			self::newPaymentIdGeneratorStub()
		);
		return $payment;
	}

	public static function newBankTransferPayment(): BankTransferPayment {
		return BankTransferPayment::create(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::Quarterly,
			PaymentReferenceCode::newFromString( self::PAYMENT_BANK_TRANSFER_CODE )
		);
	}

	public static function newCreditCardPayment(): CreditCardPayment {
		return new CreditCardPayment(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::Quarterly
		);
	}

	public static function newBookedCreditCardPayment(): CreditCardPayment {
		$payment = self::newCreditCardPayment();
		$payment->bookPayment(
			self::newCreditCardBookingData(),
			self::newPaymentIdGeneratorStub()
		);
		return $payment;
	}

	public static function newCreditCardBookingData(): array {
		return [ 'transactionId' => self::CREDIT_CARD_TRANSACTION_ID ];
	}

}
