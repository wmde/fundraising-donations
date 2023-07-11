<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationExistsChecker;

class FailingDonationExistsChecker implements DonationExistsChecker {

	public function donationExists( int $donationId ): bool {
		return false;
	}
}
