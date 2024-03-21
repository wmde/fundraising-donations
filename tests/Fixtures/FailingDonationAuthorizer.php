<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;

class FailingDonationAuthorizer implements DonationAuthorizationChecker {

	public function userCanModifyDonation( int $donationId ): bool {
		return false;
	}

	public function systemCanModifyDonation( int $donationId ): bool {
		return false;
	}

	public function canAccessDonation( int $donationId ): bool {
		return false;
	}
}
