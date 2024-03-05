<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\ReadModel;

use DateTime;

class Comment {
	public function __construct(
		public readonly string $authorName,
		public readonly float $donationAmount,
		public readonly string $commentText,
		public readonly DateTime $donationTime,
		public readonly int $donationId
	) {
	}
}
