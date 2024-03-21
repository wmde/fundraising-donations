<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases;

/**
 * @todo Move into consolidated booking use case namespace
 */
class NotificationRequest {

	/**
	 * @param array<string,scalar> $bookingData
	 * @param int $donationId
	 */
	public function __construct(
		public readonly array $bookingData,
		public readonly int $donationId
	) {
	}
}
