<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

trait NewsletterTrait {
	private bool $newsletter;

	public function subscribeToNewsletter(): void {
		$this->newsletter = true;
	}

	public function unsubscribeFromNewsletter(): void {
		$this->newsletter = false;
	}

	public function wantsNewsletter(): bool {
		return $this->newsletter;
	}
}
