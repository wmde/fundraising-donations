<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\ScrubbedAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\ScrubbedName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

/**
 * We need to set a donor when we recieve recurring donations from PayPal. The parent
 * donation is a scrubbed donor, but using that will cause is_scrubbed to be set on
 * the follow up donation before it is exported.
 */
class RecurringDonor extends AbstractDonor {
	use MailingListTrait;
	use ReceiptTrait;

	public function __construct(
		ScrubbedName $name,
		private readonly DonorType $originalDonorType
	) {
		$this->name = $name;
		$this->physicalAddress = new ScrubbedAddress();
		$this->emailAddress = '';

		// We don't care about these for recurring donations
		$this->unsubscribeFromMailingList();
		$this->declineReceipt();
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
}
