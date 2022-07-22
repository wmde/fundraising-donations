<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation;

use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;

class ModerationResult {
	/**
	 * @var ModerationReason[]
	 */
	private array $moderationReasons = [];

	public function needsModeration(): bool {
		return count( $this->moderationReasons ) > 0;
	}

	public function addModerationReason( ModerationReason $reason ): void {
		$this->moderationReasons[] = $reason;
	}

	/**
	 * @return ModerationReason[]
	 */
	public function getViolations(): array {
		return $this->moderationReasons;
	}
}
