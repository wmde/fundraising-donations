<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

/**
 * @deprecated The access tokens are an HTTP layer detail that does not belong in the domain.
 *             You should access this information using the AuthenticationToken entity in the Application or Op Center.
 */
class DonationData {

	private ?string $accessToken = null;
	private ?string $updateToken = null;
	private ?string $updateTokenExpiry = null;

	public function getAccessToken(): ?string {
		return $this->accessToken;
	}

	public function setAccessToken( ?string $token ): void {
		$this->accessToken = $token;
	}

	public function getUpdateToken(): ?string {
		return $this->updateToken;
	}

	public function setUpdateToken( ?string $updateToken ): void {
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
	public function setUpdateTokenExpiry( ?string $updateTokenExpiry ): void {
		$this->updateTokenExpiry = $updateTokenExpiry;
	}

}
