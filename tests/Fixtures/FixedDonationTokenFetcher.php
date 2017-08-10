<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\Frontend\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\Frontend\DonationContext\Authorization\DonationTokenFetchingException;
use WMDE\Fundraising\Frontend\DonationContext\Authorization\DonationTokens;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class FixedDonationTokenFetcher implements DonationTokenFetcher {

	private $tokens;

	public function __construct( DonationTokens $tokens ) {
		$this->tokens = $tokens;
	}

	/**
	 * @param int $donationId
	 *
	 * @return DonationTokens
	 * @throws DonationTokenFetchingException
	 */
	public function getTokens( int $donationId ): DonationTokens {
		return $this->tokens;
	}

}
