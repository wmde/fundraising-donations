<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

class DonationTrackingInfo {

	public function __construct(
		public readonly string $tracking = '',
		public readonly int $totalImpressionCount = 0,
		public readonly int $singleBannerImpressionCount = 0,
	) {
	}

	public static function newBlankTrackingInfo(): self {
		return new self();
	}

}
