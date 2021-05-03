<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use TheSeer\Tokenizer\Token;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Authorization\TokenSet;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SucceedingDonationAuthorizer implements DonationAuthorizer {

	private TokenSet $tokenSet;

	public function __construct() {
		$this->tokenSet = new TokenSet( '~succeeding update token~', '~succeeding access token~' );
	}

	public function userCanModifyDonation( int $donationId ): bool {
		return true;
	}

	public function systemCanModifyDonation( int $donationId ): bool {
		return true;
	}

	public function canAccessDonation( int $donationId ): bool {
		return true;
	}

	public function getTokensForDonation( int $donationId ): TokenSet {
		return $this->tokenSet;
	}

	public function setTokenSet( TokenSet $tokenSet ): void {
		$this->tokenSet = $tokenSet;
	}
}
