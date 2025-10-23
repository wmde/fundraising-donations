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

	/**
	 * This method generates the concatenated tracking information for storing the "tracking" key in the data blob of the Doctrine Entity.
	 *
	 * You can remove this method when all code in the Fundraising Operation Center no longer uses the data blob to look up the tracking.
	 * See https://phabricator.wikimedia.org/T328075
	 *
	 * @return string
	 */
	public function getTrackingString(): string {
		if ( $this->campaign === '' ) {
			return '';
		}
		if ( $this->keyword === '' ) {
			return strtolower( $this->campaign );
		}
		return strtolower( sprintf( '%s/%s', $this->campaign, $this->keyword ) );
	}
}
