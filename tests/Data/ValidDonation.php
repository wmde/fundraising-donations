<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use DateTimeImmutable;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\EmailDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

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

	public const OPTS_INTO_NEWSLETTER = true;
	public const TRACKING_BANNER_IMPRESSION_COUNT = 1;
	public const TRACKING_TOTAL_IMPRESSION_COUNT = 3;

	public const TRACKING_CAMPAIGN = 'test';

	public const TRACKING_KEYWORD = 'gelb';

	/**
	 * "tracking" is the name of the property on the object, "TRACKING" is our prefix, hence TRACKING_TRACKING
	 *
	 * @deprecated use {@see self::TRACKING_KEYWORD} and {@see self::TRACKING_CAMPAIGN}
	 */
	public const TRACKING_TRACKING = 'test/gelb';
	public const COMMENT_TEXT = 'For great justice!';
	public const COMMENT_IS_PUBLIC = true;

	public const COMMENT_AUTHOR_DISPLAY_NAME = 'Such a tomato';

	public static function newBankTransferDonation( int $donationId = 1 ): Donation {
		return self::createDonation(
			$donationId,
			ValidPayments::newBankTransferPayment(),
		);
	}

	public static function newSofortDonation( int $donationId = 1 ): Donation {
		return self::createDonation(
			$donationId,
			ValidPayments::newSofortPayment(),
		);
	}

	public static function newDirectDebitDonation( int $donationId = 1 ): Donation {
		return self::createDonation(
			$donationId,
			ValidPayments::newDirectDebitPayment(),
		);
	}

	public static function newBookedPayPalDonation( int $donationId = 1, string $transactionId = ValidPayments::PAYPAL_TRANSACTION_ID ): Donation {
		return self::createDonation(
			$donationId,
			ValidPayments::newBookedPayPalPayment( $transactionId ),
		);
	}

	public static function newIncompletePayPalDonation( int $donationId = 1 ): Donation {
		return self::createDonation(
			$donationId,
			ValidPayments::newPayPalPayment(),
		);
	}

	public static function newIncompleteSofortDonation( int $donationId = 1 ): Donation {
		return self::newSofortDonation( $donationId );
	}

	public static function newCompletedSofortDonation( int $donationId = 1 ): Donation {
		$payment = ValidPayments::newCompletedSofortPayment();
		return self::createDonation(
			$donationId,
			$payment,
		);
	}

	public static function newIncompleteAnonymousPayPalDonation( int $donationId = 1 ): Donation {
		return self::createAnonymousDonation(
			$donationId,
			ValidPayments::newPayPalPayment(),
		);
	}

	public static function newBookedAnonymousPayPalDonation( int $donationId = 1 ): Donation {
		return self::createAnonymousDonation(
			$donationId,
			ValidPayments::newBookedPayPalPayment(),
		);
	}

	public static function newBookedAnonymousPayPalDonationUpdate( int $donationId ): Donation {
		return self::createAnonymousDonation(
			$donationId,
			ValidPayments::newBookedPayPalPayment(),
		);
	}

	public static function newBookedCreditCardDonation( int $donationId = 1 ): Donation {
		$payment = ValidPayments::newBookedCreditCardPayment();
		return self::createDonation(
			$donationId,
			$payment,
		);
	}

	public static function newIncompleteCreditCardDonation( int $donationId = 1 ): Donation {
		return self::createDonation(
			$donationId,
			ValidPayments::newCreditCardPayment(),
		);
	}

	public static function newIncompleteAnonymousCreditCardDonation( int $donationId = 1 ): Donation {
		return self::createAnonymousDonation(
			$donationId,
			ValidPayments::newCreditCardPayment(),
		);
	}

	public static function newCancelledPayPalDonation( int $donationId = 1 ): Donation {
		return self::createCancelledDonation(
			$donationId,
			ValidPayments::newPayPalPayment()
		);
	}

	public static function newCancelledBankTransferDonation( int $donationId = 1 ): Donation {
		return self::createCancelledDonation(
			$donationId,
			ValidPayments::newBankTransferPayment()
		);
	}

	public static function newCompanyBankTransferDonation( int $donationId = 1 ): Donation {
		$donation = self::createDonation(
			$donationId,
			ValidPayments::newBankTransferPayment(),
		);
		$donation->setDonor( self::newCompanyDonor() );
		return $donation;
	}

	private static function createDonation( int $donationId, Payment $payment ): Donation {
		$donor = self::newDonor();
		$donor->subscribeToMailingList();
		return new Donation(
			$donationId,
			$donor,
			$payment->getId(),
			self::newTrackingInfo(),
			self::newDonatedOnDate()
		);
	}

	private static function createCancelledDonation( int $donationId, Payment $payment ): Donation {
		$donor = self::newDonor();
		$donor->subscribeToMailingList();
		$donation = new Donation(
			$donationId,
			$donor,
			$payment->getId(),
			self::newTrackingInfo(),
			self::newDonatedOnDate()
		);
		$donation->cancelWithoutChecks();
		return $donation;
	}

	private static function createAnonymousDonation( int $donationId, Payment $payment ): Donation {
		return new Donation(
			$donationId,
			new AnonymousDonor(),
			$payment->getId(),
			self::newTrackingInfo(),
			self::newDonatedOnDate()
		);
	}

	public static function newDonor(): PersonDonor {
		return new PersonDonor(
			self::newPersonName(),
			self::newAddress(),
			self::DONOR_EMAIL_ADDRESS
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
		return new DonationTrackingInfo(
			campaign: self::TRACKING_CAMPAIGN,
			keyword: self::TRACKING_KEYWORD,
			totalImpressionCount: self::TRACKING_TOTAL_IMPRESSION_COUNT,
			singleBannerImpressionCount: self::TRACKING_BANNER_IMPRESSION_COUNT
		);
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

	private static function newCompanyName(): CompanyContactName {
		return new CompanyContactName(
			self::DONOR_COMPANY,
			self::DONOR_FIRST_NAME,
			self::DONOR_LAST_NAME,
			self::DONOR_SALUTATION,
			self::DONOR_TITLE
		);
	}

	public static function newEmailOnlyDonor(): EmailDonor {
		return new EmailDonor( self::newPersonName(), self::DONOR_EMAIL_ADDRESS );
	}

	public static function newDonatedOnDate(): DateTimeImmutable {
		return new DateTimeImmutable( '2015-12-14 16:25:44' );
	}

}
