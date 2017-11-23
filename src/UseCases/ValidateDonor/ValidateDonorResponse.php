<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\UseCases\ValidateDonor;

use WMDE\FunValidators\ValidationResult;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ValidateDonorResponse extends ValidationResult {

	public const VIOLATION_MISSING = 'missing';
	public const VIOLATION_NOT_POSTCODE = 'not-postcode';
	public const VIOLATION_WRONG_LENGTH = 'wrong-length';

	public const SOURCE_EMAIL = 'email';
	public const SOURCE_COMPANY = 'companyName';
	public const SOURCE_FIRST_NAME = 'firstName';
	public const SOURCE_LAST_NAME = 'lastName';
	public const SOURCE_SALUTATION = 'salutation';
	public const SOURCE_TITLE = 'title';
	public const SOURCE_STREET_ADDRESS = 'street';
	public const SOURCE_POSTAL_CODE = 'postcode';
	public const SOURCE_CITY = 'city';
	public const SOURCE_COUNTRY = 'country';

}
