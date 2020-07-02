<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class FailingDonationAuthorizer implements DonationAuthorizer {

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
