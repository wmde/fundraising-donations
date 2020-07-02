<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;

class FixedTokenGenerator implements TokenGenerator {

	public const TOKEN = 'fixed_token';
	public const EXPIRY_DATE = '3000-01-01 00:00:00';

	public function generateToken(): string {
		return self::TOKEN;
	}

	public function generateTokenExpiry(): \DateTime {
		return new \DateTime( self::EXPIRY_DATE );
	}

}
