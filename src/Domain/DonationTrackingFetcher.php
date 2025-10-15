<?php

namespace WMDE\Fundraising\DonationContext\Domain;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationTracking;

interface DonationTrackingFetcher {
	public function getTrackingId( string $campaign, string $keyword ): int;

	public function getTracking( string $campaign, string $keyword ): DonationTracking;
}
