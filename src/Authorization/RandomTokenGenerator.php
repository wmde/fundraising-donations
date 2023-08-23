<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Authorization;

/**
 * @deprecated Tokens should be generated outside the bounded context
 */
class RandomTokenGenerator implements TokenGenerator {

	public function __construct(
		private readonly int $tokenLength,
		private readonly \DateInterval $validityTimeSpan
	) {
	}

	public function generateToken(): string {
		return bin2hex( random_bytes( max( 1, $this->tokenLength ) ) );
	}

	public function generateTokenExpiry(): \DateTime {
		return ( new \DateTime() )->add( $this->validityTimeSpan );
	}

}
