<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\RestoreDonation;

class RestoreDonationSuccessResponse {
	public function __construct( public readonly int $donationId ) {
	}
}
