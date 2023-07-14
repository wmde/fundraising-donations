<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizationChecker;

/**
 * @license GPL-2.0-or-later
 */
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
