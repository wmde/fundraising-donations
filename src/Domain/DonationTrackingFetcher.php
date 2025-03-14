<?php

namespace WMDE\Fundraising\DonationContext\Domain;

interface DonationTrackingFetcher {
	public function getTrackingId( string $campaign, string $keyword ): int;
}
