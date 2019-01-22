<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ValidateDonor;

use WMDE\FunValidators\ValidationResult;

/**
 * @licence GNU GPL v2+
 */
class ValidateDonorAddressResponse extends ValidationResult {

	public const VIOLATION_MISSING = 'missing';
	public const VIOLATION_NOT_POSTCODE = 'not-postcode';
	public const VIOLATION_WRONG_LENGTH = 'wrong-length';
	public const VIOLATION_WRONG_TYPE = 'wrong_donor_type';

	public const SOURCE_COMPANY = 'companyName';
	public const SOURCE_FIRST_NAME = 'firstName';
	public const SOURCE_LAST_NAME = 'lastName';
	public const SOURCE_SALUTATION = 'salutation';
	public const SOURCE_TITLE = 'title';
	public const SOURCE_STREET_ADDRESS = 'street';
	public const SOURCE_POSTAL_CODE = 'postcode';
	public const SOURCE_CITY = 'city';
	public const SOURCE_COUNTRY = 'country';
	public const SOURCE_ADDRESS_TYPE = 'addressType';

}
