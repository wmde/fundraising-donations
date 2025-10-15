<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

class DonationTrackingInfo {

	public function __construct(
		public readonly string $campaign = '',
		public readonly string $keyword = '',
		public readonly int $totalImpressionCount = 0,
		public readonly int $singleBannerImpressionCount = 0,
	) {
	}

	// phpcs:disable
	/**
	 * @deprecated Remove when the legacy converters no longer access tracking
	 */
	public string $tracking {
		get => $this->campaign ? sprintf( '%s/%s', $this->campaign, $this->keyword ) : '';
	}
	// phpcs:enable

	/**
	 * @deprecated Remove when {@see \WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest} no longer needs this
	 */
	public static function newWithTrackingString( string $tracking, int $totalImpressionCount, int $singleBannerImpressionCount ): self {
		$trackingPairs = explode( '/', $tracking );
		return new self( $trackingPairs[0], $trackingPairs[1] ?? '', $totalImpressionCount, $singleBannerImpressionCount );
	}

	public static function newBlankTrackingInfo(): self {
		return new self();
	}
}
