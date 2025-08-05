<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

class CancelDonationSuccessResponse {
	public function __construct(
		public readonly int $donationId
	) {
	}
}
