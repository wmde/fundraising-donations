<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\NoAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\NoName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

class AnonymousDonor extends AbstractDonor {

	public function __construct() {
		$this->name = new NoName();
		$this->physicalAddress = new NoAddress();
		$this->emailAddress = '';
	}

	public function isPrivatePerson(): bool {
		return false;
	}

	public function isCompany(): bool {
		return false;
	}

	public function getDonorType(): DonorType {
		return DonorType::ANONYMOUS;
	}

	public function subscribeToMailingList(): void {
		// Do nothing, this donor doesn't support newsletters
	}

	public function unsubscribeFromMailingList(): void {
		// Do nothing, this donor doesn't support newsletters
	}

	public function isSubscribedToMailingList(): bool {
		return false;
	}

	public function requireReceipt(): void {
		// Do nothing, this donor doesn't support receipts
	}

	public function declineReceipt(): void {
		// Do nothing, this donor doesn't support receipts
	}

	public function wantsReceipt(): bool {
		return false;
	}

}
