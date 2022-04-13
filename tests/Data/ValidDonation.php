<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\EmailDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentReferenceCode;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Domain\PaymentIdRepository;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ValidDonation {

	public const DONOR_FIRST_NAME = 'Jeroen';
	public const DONOR_LAST_NAME = 'De Dauw';
	public const DONOR_SALUTATION = 'nyan';
	public const DONOR_TITLE = 'nyan';
	public const DONOR_FULL_NAME = 'nyan Jeroen De Dauw';
	public const DONOR_COMPANY = 'Fluffy Beings Ltd.';

	public const DONOR_CITY = 'Berlin';
	public const DONOR_POSTAL_CODE = '12345';
	public const DONOR_COUNTRY_CODE = 'DE';
	public const DONOR_STREET_ADDRESS = 'Nyan Street';

	public const DONOR_EMAIL_ADDRESS = 'foo@bar.baz';

	// Use fractional value to detect floating point issues
	public const DONATION_AMOUNT = 13.37;
	public const PAYMENT_INTERVAL_IN_MONTHS = 3;

	public const PAYMENT_BANK_ACCOUNT = '0648489890';
	public const PAYMENT_BANK_CODE = '50010517';
	public const PAYMENT_BANK_NAME = 'ING-DiBa';
	public const PAYMENT_BIC = 'INGDDEFFXXX';
	public const PAYMENT_IBAN = 'DE12500105170648489890';

	public const PAYMENT_BANK_TRANSFER_CODE = 'XW-DAR-E99-X';

	public const PAYMENT_VALUATION_DATE = '2021-05-15 21:12:00';

	public const PAYMENT_SOFORT_VALUATION_DATE = '2021-06-15T05:07:00Z';

	public const OPTS_INTO_NEWSLETTER = Donation::OPTS_INTO_NEWSLETTER;
	public const TRACKING_BANNER_IMPRESSION_COUNT = 1;
	public const TRACKING_TOTAL_IMPRESSION_COUNT = 3;
	// "tracking" is the name of the property on the object, "TRACKING" is our prefix, hence TRACKING_TRACKING

	public const TRACKING_TRACKING = 'test/gelb';
	public const PAYPAL_TRANSACTION_ID = '61E67681CH3238416';
	private const PAYPAL_DATE_FORMAT = 'H:i:s M d, Y e';
	public const PAYPAL_PAYER_ID = 'HE373U84ENFYQ';

	public const CREDIT_CARD_TRANSACTION_ID = '7788998877';
	public const COMMENT_TEXT = 'For great justice!';
	public const COMMENT_IS_PUBLIC = true;

	public const COMMENT_AUTHOR_DISPLAY_NAME = 'Such a tomato';
	public const SOFORT_DONATION_CONFIRMED_AT = '-1 hour';
	public const SOFORT_TRANSACTION_ID = '44544554';

	public static function newBankTransferDonation(): Donation {
		return self::createDonation(
			self::newBankTransferPayment(),
			Donation::STATUS_PROMISE
		);
	}

	public static function newSofortDonation(): Donation {
		return self::createDonation(
			self::newSofortPayment(),
			Donation::STATUS_PROMISE
		);
	}

	public static function newDirectDebitDonation(): Donation {
		return self::createDonation(
			self::newDirectDebitPayment(),
			Donation::STATUS_NEW
		);
	}

	public static function newBookedPayPalDonation( string $transactionId = self::PAYPAL_TRANSACTION_ID ): Donation {
		return self::createDonation(
			self::newBookedPayPalPayment( $transactionId ),
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	private static function newBookedPayPalPayment( string $transactionId = self::PAYPAL_TRANSACTION_ID ): PayPalPayment {
		$payment = self::newPayPalPayment();
		$payment->bookPayment(
			[
				'payer_id' => self::PAYPAL_PAYER_ID,
				'txn_id' => $transactionId,
				'payment_date' => ( new \DateTimeImmutable( self::PAYMENT_VALUATION_DATE ) )->format( self::PAYPAL_DATE_FORMAT )
			],
			self::newPaymentIdGeneratorStub()
		);
		return $payment;
	}

	public static function newIncompletePayPalDonation(): Donation {
		return self::createDonation(
			self::newPayPalPayment(),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newIncompleteSofortDonation(): Donation {
		return self::newSofortDonation();
	}

	public static function newCompletedSofortDonation(): Donation {
		$payment = self::newSofortPayment();
		$payment->bookPayment(
			[ 'valuationDate' => self::PAYMENT_SOFORT_VALUATION_DATE, 'transactionId' => self::SOFORT_TRANSACTION_ID ],
			self::newPaymentIdGeneratorStub()
		);
		return self::createDonation(
			$payment,
			Donation::STATUS_PROMISE
		);
	}

	public static function newIncompleteAnonymousPayPalDonation(): Donation {
		return self::createAnonymousDonation(
			self::newPayPalPayment(),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newBookedAnonymousPayPalDonation(): Donation {
		return self::createAnonymousDonation(
			self::newBookedPayPalPayment(),
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	public static function newBookedAnonymousPayPalDonationUpdate( int $donationId ): Donation {
		return self::createAnonymousDonationWithId(
			$donationId,
			self::newBookedPayPalPayment(),
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	public static function newBookedCreditCardDonation(): Donation {
		$payment = self::newCreditCardPayment();
		$payment->bookPayment(
			self::newCreditCardBookingData(),
			self::newPaymentIdGeneratorStub()
		);
		return self::createDonation(
			$payment,
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	public static function newCreditCardBookingData(): array {
		return [ 'transactionId' => self::CREDIT_CARD_TRANSACTION_ID, 'amount' => self::DONATION_AMOUNT * 100 ];
	}

	public static function newIncompleteCreditCardDonation(): Donation {
		return self::createDonation(
			self::newCreditCardPayment(),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newIncompleteAnonymousCreditCardDonation(): Donation {
		return self::createAnonymousDonation(
			self::newCreditCardPayment(),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newCancelledPayPalDonation(): Donation {
		return self::createCancelledDonation(
			self::newPayPalPayment()
		);
	}

	public static function newCancelledBankTransferDonation(): Donation {
		return self::createCancelledDonation(
			self::newBankTransferPayment()
		);
	}

	public static function newCompanyBankTransferDonation(): Donation {
		$donation = self::createDonation(
			self::newBankTransferPayment(),
			Donation::STATUS_NEW
		);
		$donation->setDonor( self::newCompanyDonor() );
		return $donation;
	}

	private static function createDonation( Payment $payment, string $status ): Donation {
		return new Donation(
			null,
			$status,
			self::newDonor(),
			$payment,
			self::OPTS_INTO_NEWSLETTER,
			self::newTrackingInfo()
		);
	}

	private static function createCancelledDonation( Payment $payment ): Donation {
		$donation = new Donation(
			null,
			Donation::STATUS_NEW,
			self::newDonor(),
			$payment,
			self::OPTS_INTO_NEWSLETTER,
			self::newTrackingInfo()
		);
		$donation->cancelWithoutChecks();
		return $donation;
	}

	private static function createAnonymousDonation( Payment $payment, string $status ): Donation {
		return new Donation(
			null,
			$status,
			new AnonymousDonor(),
			$payment,
			false,
			self::newTrackingInfo()
		);
	}

	private static function createAnonymousDonationWithId( int $donationId, Payment $payment, string $status ): Donation {
		return new Donation(
			$donationId,
			$status,
			new AnonymousDonor(),
			$payment,
			false,
			self::newTrackingInfo()
		);
	}

	public static function newDonor(): PersonDonor {
		return new PersonDonor(
			self::newPersonName(),
			self::newAddress(),
			self::DONOR_EMAIL_ADDRESS
		);
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

	private static function newPersonName(): PersonName {
		return new PersonName(
			self::DONOR_FIRST_NAME,
			self::DONOR_LAST_NAME,
			self::DONOR_SALUTATION,
			self::DONOR_TITLE
		);
	}

	private static function newAddress(): PostalAddress {
		return new PostalAddress(
			self::DONOR_STREET_ADDRESS,
			self::DONOR_POSTAL_CODE,
			self::DONOR_CITY,
			self::DONOR_COUNTRY_CODE
		);
	}

	public static function newTrackingInfo(): DonationTrackingInfo {
		$trackingInfo = DonationTrackingInfo::newBlankTrackingInfo();

		$trackingInfo->setSingleBannerImpressionCount( self::TRACKING_BANNER_IMPRESSION_COUNT );
		$trackingInfo->setTotalImpressionCount( self::TRACKING_TOTAL_IMPRESSION_COUNT );
		$trackingInfo->setTracking( self::TRACKING_TRACKING );

		return $trackingInfo->freeze()->assertNoNullFields();
	}

	public static function newPublicComment(): DonationComment {
		return new DonationComment(
			self::COMMENT_TEXT,
			self::COMMENT_IS_PUBLIC,
			self::COMMENT_AUTHOR_DISPLAY_NAME
		);
	}

	public static function newCompanyDonor(): CompanyDonor {
		return new CompanyDonor(
			self::newCompanyName(),
			self::newAddress(),
			self::DONOR_EMAIL_ADDRESS
		);
	}

	private static function newCompanyName(): CompanyName {
		return new CompanyName( self::DONOR_COMPANY );
	}

	public static function newEmailOnlyDonor(): EmailDonor {
		return new EmailDonor( self::newPersonName(), self::DONOR_EMAIL_ADDRESS );
	}

	private static function newSofortPayment(): SofortPayment {
		return SofortPayment::create(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::OneTime,
			PaymentReferenceCode::newFromString( self::PAYMENT_BANK_TRANSFER_CODE )
		);
	}

	private static function newPaymentIdGeneratorStub() {
		return new class implements PaymentIdRepository {
			public function getNewID(): int {
				throw new \LogicException( 'Id generator should never be called - this is only for followup payments' );
			}

		};
	}

	public static function newPayPalPayment(): PayPalPayment {
		return new PayPalPayment(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::Quarterly
		);
	}

	public static function newCreditCardPayment(): CreditCardPayment {
		return new CreditCardPayment(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::Quarterly
		);
	}

	public static function newBankTransferPayment(): BankTransferPayment {
		return BankTransferPayment::create(
			1,
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			PaymentInterval::Quarterly,
			PaymentReferenceCode::newFromString( self::PAYMENT_BANK_TRANSFER_CODE )
		);
	}

}
