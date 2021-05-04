<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Authorization;

/**
 * TokenSet can be used to create authorization URL parameters for accessing donations.
 */
class TokenSet {

	private string $updateToken;
	private string $accessToken;

	public function __construct( string $updateToken, string $accessToken ) {
		$this->updateToken = $updateToken;
		$this->accessToken = $accessToken;
	}

	public function getUpdateToken(): string {
		return $this->updateToken;
	}

	public function getAccessToken(): string {
		return $this->accessToken;
	}

}
