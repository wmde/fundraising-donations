<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

interface DonationIdRepository {
	public function getNewId(): int;
}
