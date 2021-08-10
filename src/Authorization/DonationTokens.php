<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Authorization;

/**
 * @license GPL-2.0-or-later
 */
class DonationTokens {

	private string $accessToken;
	private string $updateToken;

	public function __construct( string $accessToken, string $updateToken ) {
		if ( $accessToken === '' ) {
			throw new \UnexpectedValueException( 'Access token cannot be empty' );
		}

		if ( $updateToken === '' ) {
			throw new \UnexpectedValueException( 'Update token cannot be empty' );
		}

		$this->accessToken = $accessToken;
		$this->updateToken = $updateToken;
	}

	public function getAccessToken(): string {
		return $this->accessToken;
	}

	public function getUpdateToken(): string {
		return $this->updateToken;
	}

}
