<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;

class StaticDonationIdRepository implements DonationIdRepository {

	public const DONATION_ID = 42;

	public function getNewId(): int {
		return self::DONATION_ID;
	}
}
