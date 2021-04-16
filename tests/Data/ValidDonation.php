<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use DateTime;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationPayment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\EmailDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardTransactionData;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Infrastructure\CreditCardExpiry;

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

	public const PAYMENT_BANK_TRANSFER_CODE = 'pink fluffy unicorns';

	public const OPTS_INTO_NEWSLETTER = Donation::OPTS_INTO_NEWSLETTER;

	public const TRACKING_BANNER_IMPRESSION_COUNT = 1;
	public const TRACKING_TOTAL_IMPRESSION_COUNT = 3;
	// "tracking" is the name of the property on the object, "TRACKING" is our prefix, hence TRACKING_TRACKING
	public const TRACKING_TRACKING = 'test/gelb';

	public const PAYPAL_TRANSACTION_ID = '61E67681CH3238416';

	public const CREDIT_CARD_TRANSACTION_ID = '';
	public const CREDIT_CARD_EXPIRY_YEAR = 2001;
	public const CREDIT_CARD_EXPIRY_MONTH = 9;

	public const COMMENT_TEXT = 'For great justice!';
	public const COMMENT_IS_PUBLIC = true;
	public const COMMENT_AUTHOR_DISPLAY_NAME = 'Such a tomato';

	public const SOFORT_DONATION_CONFIRMED_AT = '-1 hour';

	public static function newBankTransferDonation(): Donation {
		return self::createDonation(
			new BankTransferPayment( self::PAYMENT_BANK_TRANSFER_CODE ),
			Donation::STATUS_PROMISE
		);
	}

	public static function newSofortDonation(): Donation {
		return self::createDonation(
			new SofortPayment( self::PAYMENT_BANK_TRANSFER_CODE ),
			Donation::STATUS_PROMISE
		);
	}

	public static function newDirectDebitDonation(): Donation {
		return self::createDonation(
			new DirectDebitPayment( self::newBankData() ),
			Donation::STATUS_NEW
		);
	}

	public static function newBookedPayPalDonation( string $transactionId = self::PAYPAL_TRANSACTION_ID ): Donation {
		$payPalData = new PayPalData();
		$payPalData->setPaymentId( $transactionId );

		return self::createDonation(
			new PayPalPayment( $payPalData ),
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	public static function newIncompletePayPalDonation(): Donation {
		return self::createDonation(
			new PayPalPayment( new PayPalData() ),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newIncompleteSofortDonation(): Donation {
		return self::createDonation(
			new SofortPayment( self::PAYMENT_BANK_TRANSFER_CODE ),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newCompletedSofortDonation(): Donation {
		$payment = new SofortPayment( self::PAYMENT_BANK_TRANSFER_CODE );
		$payment->setConfirmedAt( new DateTime( self::SOFORT_DONATION_CONFIRMED_AT ) );
		return self::createDonation(
			$payment,
			Donation::STATUS_PROMISE
		);
	}

	public static function newIncompleteAnonymousPayPalDonation(): Donation {
		return self::createAnonymousDonation(
			new PayPalPayment( new PayPalData() ),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newBookedAnonymousPayPalDonation(): Donation {
		$payPalData = new PayPalData();
		$payPalData->setPaymentId( self::PAYPAL_TRANSACTION_ID );

		return self::createAnonymousDonation(
			new PayPalPayment( $payPalData ),
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	public static function newBookedAnonymousPayPalDonationUpdate( int $donationId ): Donation {
		$payPalData = new PayPalData();
		$payPalData->setPaymentId( self::PAYPAL_TRANSACTION_ID );

		return self::createAnonymousDonationWithId(
			$donationId,
			new PayPalPayment( $payPalData ),
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	public static function newBookedCreditCardDonation(): Donation {
		$creditCardData = new CreditCardTransactionData();
		$creditCardData->setTransactionId( self::CREDIT_CARD_TRANSACTION_ID );
		$creditCardData->setCardExpiry( new CreditCardExpiry( self::CREDIT_CARD_EXPIRY_MONTH, self::CREDIT_CARD_EXPIRY_YEAR ) );

		return self::createDonation(
			new CreditCardPayment( $creditCardData ),
			Donation::STATUS_EXTERNAL_BOOKED
		);
	}

	public static function newIncompleteCreditCardDonation(): Donation {
		return self::createDonation(
			new CreditCardPayment( new CreditCardTransactionData() ),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newIncompleteAnonymousCreditCardDonation(): Donation {
		return self::createAnonymousDonation(
			new CreditCardPayment( new CreditCardTransactionData() ),
			Donation::STATUS_EXTERNAL_INCOMPLETE
		);
	}

	public static function newCancelledPayPalDonation(): Donation {
		return self::createCancelledDonation(
			new PayPalPayment( new PayPalData() )
		);
	}

	public static function newCancelledBankTransferDonation(): Donation {
		return self::createCancelledDonation(
			new BankTransferPayment( self::PAYMENT_BANK_TRANSFER_CODE )
		);
	}

	public static function newCompanyBankTransferDonation(): Donation {
		$donation = self::createDonation(
			new BankTransferPayment( self::PAYMENT_BANK_TRANSFER_CODE ),
			Donation::STATUS_NEW
		);
		$donation->setDonor( self::newCompanyDonor() );
		return $donation;
	}

	private static function createDonation( PaymentMethod $paymentMethod, string $status ): Donation {
		return new Donation(
			null,
			$status,
			self::newDonor(),
			self::newDonationPayment( $paymentMethod ),
			self::OPTS_INTO_NEWSLETTER,
			self::newTrackingInfo()
		);
	}

	private static function createCancelledDonation( PaymentMethod $paymentMethod ): Donation {
		$donation = new Donation(
			null,
			Donation::STATUS_NEW,
			self::newDonor(),
			self::newDonationPayment( $paymentMethod ),
			self::OPTS_INTO_NEWSLETTER,
			self::newTrackingInfo()
		);
		$donation->cancelWithoutChecks();
		return $donation;
	}

	private static function createAnonymousDonation( PaymentMethod $paymentMethod, string $status ): Donation {
		return new Donation(
			null,
			$status,
			new AnonymousDonor(),
			self::newDonationPayment( $paymentMethod ),
			false,
			self::newTrackingInfo()
		);
	}

	private static function createAnonymousDonationWithId( int $donationId, PaymentMethod $paymentMethod, string $status ): Donation {
		return new Donation(
			$donationId,
			$status,
			new AnonymousDonor(),
			self::newDonationPayment( $paymentMethod ),
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

	private static function newDonationPayment( PaymentMethod $paymentMethod ): DonationPayment {
		return new DonationPayment(
			Euro::newFromFloat( self::DONATION_AMOUNT ),
			self::PAYMENT_INTERVAL_IN_MONTHS,
			$paymentMethod
		);
	}

	public static function newDirectDebitPayment(): DonationPayment {
		return self::newDonationPayment( new DirectDebitPayment( self::newBankData() ) );
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

	public static function newBankData(): BankData {
		$bankData = new BankData();

		$bankData->setAccount( self::PAYMENT_BANK_ACCOUNT );
		$bankData->setBankCode( self::PAYMENT_BANK_CODE );
		$bankData->setBankName( self::PAYMENT_BANK_NAME );
		$bankData->setBic( self::PAYMENT_BIC );
		$bankData->setIban( new Iban( self::PAYMENT_IBAN ) );

		return $bankData->freeze()->assertNoNullFields();
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

}
