<?php

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

interface DonationExistsChecker {
	public function donationExists( int $donationId ): bool;
}
