<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

class CancelDonationRequest {

	public function __construct( private readonly int $donationId, private readonly string $authorizedUser ) {
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function isAuthorizedRequest(): bool {
		return true;
	}

	public function getUserName(): string {
		return $this->authorizedUser;
	}

}
