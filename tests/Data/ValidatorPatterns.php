<?php

declare(strict_types = 1);

namespace WMDE\Fundraising\DonationContext\Tests\Data;

class ValidatorPatterns {
	public const COUNTRY_POSTCODE = [
		'DE' => '/^[0-9]{5}$/',
		'AT' => '/^[0-9]{4}$/',
		'CH' => '/^[0-9]{4}$/',
		'BE' => '/^[0-9]{4}$/',
		'IT' => '/^[0-9]{5}$/',
		'LI' => '/^[0-9]{4}$/',
		'LU' => '/^[0-9]{4}$/',
	];

	public const ADDRESS_PATTERNS = [
		'firstName' => "/^[A-Za-z\u00C0-\u00D6\u00D8-\u00f6\u00f8-\u00ff\\s\\-\\.\\']+$/",
		'lastName' => "/^[A-Za-z\u00C0-\u00D6\u00D8-\u00f6\u00f8-\u00ff\\s\\-\\.\\']+$/",
		'postcode' => '/^.+$/',
	];
}