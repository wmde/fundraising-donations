<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AddressValidator;

class AddDonationValidationResult extends ValidationResult {

	public const SOURCE_PAYMENT_TYPE = 'zahlweise';
	public const SOURCE_PAYMENT_AMOUNT = 'amount';
	public const SOURCE_DONOR_ADDRESS_TYPE = 'addressType';
	public const SOURCE_DONOR_EMAIL = 'email';
	public const SOURCE_DONOR_COMPANY = AddressValidator::SOURCE_COMPANY;
	public const SOURCE_DONOR_FIRST_NAME = AddressValidator::SOURCE_FIRST_NAME;
	public const SOURCE_DONOR_LAST_NAME = AddressValidator::SOURCE_LAST_NAME;
	public const SOURCE_DONOR_SALUTATION = AddressValidator::SOURCE_SALUTATION;
	public const SOURCE_DONOR_TITLE = AddressValidator::SOURCE_TITLE;
	public const SOURCE_DONOR_STREET_ADDRESS = AddressValidator::SOURCE_STREET_ADDRESS;
	public const SOURCE_DONOR_POSTAL_CODE = AddressValidator::SOURCE_POSTAL_CODE;
	public const SOURCE_DONOR_CITY = AddressValidator::SOURCE_CITY;
	public const SOURCE_DONOR_COUNTRY = AddressValidator::SOURCE_COUNTRY;
	public const SOURCE_TRACKING_SOURCE = 'source';

	public const VIOLATION_FORBIDDEN_PAYMENT_TYPE_FOR_DONOR_TYPE = 'forbidden_payment_type_for_donor_type';

	public const VIOLATION_WRONG_LENGTH = 'wrong-length';

}
