<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class ValidAddDonationRequest {

	public static function getRequest(): AddDonationRequest {
		$request = new AddDonationRequest();
		$request->setAmount( Euro::newFromInt( 5 ) );
		$request->setBankData( self::newValidBankData() );
		$request->setInterval( ValidDonation::PAYMENT_INTERVAL_IN_MONTHS );
		$request->setOptIn( (string)ValidDonation::OPTS_INTO_NEWSLETTER );
		$request->setPaymentType( PaymentMethod::DIRECT_DEBIT );

		$request->setDonorType( DonorType::PERSON() );
		$request->setDonorSalutation( ValidDonation::DONOR_SALUTATION );
		$request->setDonorTitle( ValidDonation::DONOR_TITLE );
		$request->setDonorCompany( '' );
		$request->setDonorFirstName( ValidDonation::DONOR_FIRST_NAME );
		$request->setDonorLastName( ValidDonation::DONOR_LAST_NAME );
		$request->setDonorStreetAddress( ValidDonation::DONOR_STREET_ADDRESS );
		$request->setDonorPostalCode( ValidDonation::DONOR_POSTAL_CODE );
		$request->setDonorCity( ValidDonation::DONOR_CITY );
		$request->setDonorCountryCode( ValidDonation::DONOR_COUNTRY_CODE );
		$request->setDonorEmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS );

		return $request;
	}

	private static function newValidBankData(): BankData {
		$bankData = new BankData();

		$bankData->setAccount( ValidDonation::PAYMENT_BANK_ACCOUNT );
		$bankData->setBankCode( ValidDonation::PAYMENT_BANK_CODE );
		$bankData->setBankName( ValidDonation::PAYMENT_BANK_NAME );
		$bankData->setBic( ValidDonation::PAYMENT_BIC );
		$bankData->setIban( new Iban( ValidDonation::PAYMENT_IBAN ) );

		return $bankData->freeze()->assertNoNullFields();
	}
}
