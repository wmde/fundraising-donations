<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Authorization;

/**
 * @deprecated Tokens should be generated outside the bounded context
 */
interface TokenGenerator {

	public function generateToken(): string;

	public function generateTokenExpiry(): \DateTime;

}
