<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

trait MailingListTrait {
	private bool $mailingList;

	public function subscribeToMailingList(): void {
		$this->mailingList = true;
	}

	public function unsubscribeFromMailingList(): void {
		$this->mailingList = false;
	}

	public function isSubscribedToMailingList(): bool {
		return $this->mailingList;
	}
}
