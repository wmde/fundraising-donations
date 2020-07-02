<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ValidDoctrineDonation {

	/**
	 * Returns a Doctrine Donation entity equivalent to the domain entity returned
	 * by @see ValidDonation::newDirectDebitDonation
	 *
	 * @return Donation
	 */
	public static function newDirectDebitDoctrineDonation(): Donation {
		return ( new self() )->createDonation();
	}

	public static function newExportedirectDebitDoctrineDonation(): Donation {
		$donation = ( new self() )->createDonation();
		$donation->setDtGruen( new \DateTime() );
		return $donation;
	}

	public static function newPaypalDoctrineDonation(): Donation {
		$self = new self();
		$donation = $self->createDonation();
		$donation->setPaymentType( PaymentMethod::PAYPAL );
		$donation->encodeAndSetData(
			array_merge(
				$donation->getDecodedData(),
				$self->getPaypalDataArray()
			)
		);
		return $donation;
	}

	private function createDonation(): Donation {
		$donation = new Donation();

		$donation->setStatus( Donation::STATUS_NEW );

		$donation->setAmount( (string)ValidDonation::DONATION_AMOUNT );
		$donation->setPaymentIntervalInMonths( ValidDonation::PAYMENT_INTERVAL_IN_MONTHS );
		$donation->setPaymentType( PaymentMethod::DIRECT_DEBIT );

		$donation->setDonorCity( ValidDonation::DONOR_CITY );
		$donation->setDonorEmail( ValidDonation::DONOR_EMAIL_ADDRESS );
		$donation->setDonorFullName( ValidDonation::DONOR_FULL_NAME );
		$donation->setDonorOptsIntoNewsletter( ValidDonation::OPTS_INTO_NEWSLETTER );

		$donation->encodeAndSetData(
			array_merge(
				$this->getTrackingInfoArray(),
				$this->getBankDataArray(),
				$this->getDonorArray()
			)
		);

		return $donation;
	}

	private function getTrackingInfoArray(): array {
		return [
			'layout' => ValidDonation::TRACKING_LAYOUT,
			'impCount' => ValidDonation::TRACKING_TOTAL_IMPRESSION_COUNT,
			'bImpCount' => ValidDonation::TRACKING_BANNER_IMPRESSION_COUNT,
			'tracking' => ValidDonation::TRACKING_TRACKING,
			'skin' => ValidDonation::TRACKING_SKIN,
			'color' => ValidDonation::TRACKING_COLOR,
			'source' => ValidDonation::TRACKING_SOURCE,
		];
	}

	private function getBankDataArray(): array {
		return [
			'iban' => ValidDonation::PAYMENT_IBAN,
			'bic' => ValidDonation::PAYMENT_BIC,
			'konto' => ValidDonation::PAYMENT_BANK_ACCOUNT,
			'blz' => ValidDonation::PAYMENT_BANK_CODE,
			'bankname' => ValidDonation::PAYMENT_BANK_NAME,
		];
	}

	private function getDonorArray(): array {
		return array_merge(
			$this->getPersonNameArray(),
			$this->getAddressArray(),
			[ 'email' => ValidDonation::DONOR_EMAIL_ADDRESS ]
		);
	}

	private function getPersonNameArray(): array {
		return [
			'adresstyp' => 'person',
			'anrede' => ValidDonation::DONOR_SALUTATION,
			'titel' => ValidDonation::DONOR_TITLE,
			'vorname' => ValidDonation::DONOR_FIRST_NAME,
			'nachname' => ValidDonation::DONOR_LAST_NAME,
			'firma' => '',
		];
	}

	private function getAddressArray(): array {
		return [
			'strasse' => ValidDonation::DONOR_STREET_ADDRESS,
			'plz' => ValidDonation::DONOR_POSTAL_CODE,
			'ort' => ValidDonation::DONOR_CITY,
			'country' => ValidDonation::DONOR_COUNTRY_CODE,
		];
	}

	private function getPaypalDataArray(): array {
		return [
			'ext_payment_id' => '72171T32A6H345906',
			'ext_subscr_id' => 'I-DYP3HRBE7WUA',
			'ext_payment_status' => 'Completed/subscr_payment',
			'ext_payment_account' => 'QEEMF34KV3ECL',
			'ext_payment_type' => 'instant',
			'ext_payment_timestamp' => '05:10:30 May 17, 2016 PDT',
			'paypal_payer_id' => 'QEEMF34KV3ECL',
			'paypal_subscr_id' => 'I-DYP3HRBE7WUA',
			'paypal_payer_status' => 'verified',
			'paypal_first_name' => 'Max',
			'paypal_last_name' => 'Muster',
			'paypal_mc_gross' => '10.00',
			'paypal_mc_currency' => 'EUR',
			'paypal_mc_fee' => '0.47',
			'user_agent' => 'PayPal IPN ( https://www.paypal.com/ipn )',
		];
	}

}
