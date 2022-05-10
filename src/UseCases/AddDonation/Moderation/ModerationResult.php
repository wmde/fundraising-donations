<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation;

use WMDE\FunValidators\ConstraintViolation;

class ModerationResult {
	/**
	 * @var ConstraintViolation[]
	 */
	private array $moderationReasons = [];

	public function needsModeration(): bool {
		return count( $this->moderationReasons ) > 0;
	}

	public function addModerationReason( ConstraintViolation $reason ): void {
		$this->moderationReasons[] = $reason;
	}

	// When implementaing https://phabricator.wikimedia.org/T306685 we'll probably add
	// a method like getFirstViolation() to this to get the most important violation

}
