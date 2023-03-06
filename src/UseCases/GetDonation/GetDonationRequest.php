<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\GetDonation;

class GetDonationRequest {

	public function __construct( private readonly int $donationId ) {
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

}
