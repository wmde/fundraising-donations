<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

class CancelDonationRequest {

	private int $donationId;
	private ?string $authorizedUser;

	public function __construct( int $donationId, ?string $authorizedUser = null ) {
		$this->donationId = $donationId;
		$this->authorizedUser = $authorizedUser;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function isAuthorizedRequest(): bool {
		return $this->authorizedUser !== null;
	}

	public function getUserName(): string {
		if ( $this->authorizedUser == null ) {
			throw new \LogicException( "Tried to access user name of unauthorized user. Call isAuthorizedRequest() first!" );
		}
		return $this->authorizedUser;
	}

}
