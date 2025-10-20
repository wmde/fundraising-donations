<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\TrackingMigration;

class UpdateResult {

	public function __construct(
		public readonly int $updateCount,
		public readonly int $skipCount,
		public readonly int $lastUpdatedId
	) {
	}

	public function getNumProcessed(): int {
		return $this->updateCount + $this->skipCount;
	}
}
