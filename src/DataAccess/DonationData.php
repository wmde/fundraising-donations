<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

/**
 * @since 2.0
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DonationData {

	private $accessToken;
	private $updateToken;
	private $updateTokenExpiry;

	public function getAccessToken(): ?string {
		return $this->accessToken;
	}

	public function setAccessToken( ?string $token ) {
		$this->accessToken = $token;
	}

	public function getUpdateToken(): ?string {
		return $this->updateToken;
	}

	public function setUpdateToken( ?string $updateToken ) {
		$this->updateToken = $updateToken;
	}

	/**
	 * @return string|null Time in 'Y-m-d H:i:s' format
	 */
	public function getUpdateTokenExpiry(): ?string {
		return $this->updateTokenExpiry;
	}

	/**
	 * @param string|null $updateTokenExpiry Time in 'Y-m-d H:i:s' format
	 */
	public function setUpdateTokenExpiry( ?string $updateTokenExpiry ) {
		$this->updateTokenExpiry = $updateTokenExpiry;
	}


}
