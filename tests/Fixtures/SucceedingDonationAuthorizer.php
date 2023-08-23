<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;

/**
 * @license GPL-2.0-or-later
 */
class SucceedingDonationAuthorizer implements DonationAuthorizationChecker {

	public function userCanModifyDonation( int $donationId ): bool {
		return true;
	}

	public function systemCanModifyDonation( int $donationId ): bool {
		return true;
	}

	public function canAccessDonation( int $donationId ): bool {
		return true;
	}
}
