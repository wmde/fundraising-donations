<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokens;

/**
 * @deprecated
 */
class FixedDonationTokenFetcher implements DonationTokenFetcher {

	private DonationTokens $tokens;

	public function __construct( DonationTokens $tokens ) {
		$this->tokens = $tokens;
	}

	/**
	 * @param int $donationId
	 *
	 * @return DonationTokens
	 */
	public function getTokens( int $donationId ): DonationTokens {
		return $this->tokens;
	}

}
