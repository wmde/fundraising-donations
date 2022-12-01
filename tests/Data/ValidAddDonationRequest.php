<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;

class ValidAddDonationRequest {

	public static function getRequest(): AddDonationRequest {
		$request = new AddDonationRequest();
		$request->setPaymentCreationRequest( self::newPaymentCreationRequest() );
		$request->setOptsIntoNewsletter( ValidDonation::OPTS_INTO_NEWSLETTER );
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

	public static function newPaymentCreationRequest(): PaymentCreationRequest {
		return new PaymentCreationRequest(
			500,
			PaymentInterval::OneTime->value,
			'BEZ',
			ValidPayments::PAYMENT_IBAN,
			ValidPayments::PAYMENT_BIC
		);
	}
}
