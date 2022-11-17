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

	public function getDonorType(): string {
		return (string)DonorType::ANONYMOUS();
	}

	public function subscribeToNewsletter(): void {
		// Do nothing, this donor doesn't support newsletters
	}

	public function unsubscribeFromNewsletter(): void {
		// Do nothing, this donor doesn't support newsletters
	}

	public function wantsNewsletter(): bool {
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
