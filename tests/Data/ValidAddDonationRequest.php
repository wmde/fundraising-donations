<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\PaymentContext\Domain\PaymentType;

class ValidAddDonationRequest {

	public static function getRequest(): AddDonationRequest {
		$request = new AddDonationRequest();
		$request->setAmount( Euro::newFromInt( 5 ) );
		$request->setIban( ValidDonation::PAYMENT_IBAN );
		$request->setBic( ValidDonation::PAYMENT_BIC );
		$request->setInterval( ValidDonation::PAYMENT_INTERVAL_IN_MONTHS );
		$request->setOptIn( (string)ValidDonation::OPTS_INTO_NEWSLETTER );
		$request->setPaymentType( PaymentType::DirectDebit->value );

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
}
