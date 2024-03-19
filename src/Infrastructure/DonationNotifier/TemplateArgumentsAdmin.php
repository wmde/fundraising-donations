<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier;

class TemplateArgumentsAdmin {
	/**
	 * @param int $donationId
	 * @param array<string,bool> $moderationFlags Name and state of {@see \WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier}
	 * @param float $amount
	 */
	public function __construct(
		public readonly int $donationId,
		public readonly array $moderationFlags,
		public readonly float $amount,
	) {
	}
}
