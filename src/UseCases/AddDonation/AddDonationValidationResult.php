<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\FunValidators\Validators\AddressValidator;
use WMDE\FunValidators\ValidationResult;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
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

	public const VIOLATION_TOO_LOW = 'too-low';
	public const VIOLATION_TOO_HIGH = 'too-high';
	public const VIOLATION_WRONG_LENGTH = 'wrong-length';
	public const VIOLATION_NOT_MONEY = 'not-money';
	public const VIOLATION_MISSING = 'missing';
	public const VIOLATION_IBAN_BLOCKED = 'iban-blocked';
	public const VIOLATION_NOT_DATE = 'not-date';
	public const VIOLATION_NOT_PHONE_NUMBER = 'not-phone';
	public const VIOLATION_NOT_EMAIL = 'not-email';
	public const VIOLATION_NOT_POSTCODE = 'not-postcode';
	public const VIOLATION_WRONG_PAYMENT_TYPE = 'invalid_payment_type';
	public const VIOLATION_TEXT_POLICY = 'text_policy';

}