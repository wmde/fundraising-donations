<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\ScrubbedAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\ScrubbedName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

/**
 * This is a donor "placeholder" class for donations that were exported and anonymized.
 *
 * This class is different from {@see AnonymousDonor}, which is the class used where the person has actively
 * declined to provide an address.
 */
class ScrubbedDonor extends AbstractDonor {

	public function __construct( private readonly DonorType $originalDonorType ) {
		$this->name = new ScrubbedName();
		$this->physicalAddress = new ScrubbedAddress();
		$this->emailAddress = '';
	}

	public function isPrivatePerson(): bool {
		return false;
	}

	public function isCompany(): bool {
		return false;
	}

	public function getDonorType(): DonorType {
		return $this->originalDonorType;
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
