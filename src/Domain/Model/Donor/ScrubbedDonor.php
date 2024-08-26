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
 * declined to provide an address. It preserves certain settings of the original donor.
 */
class ScrubbedDonor extends AbstractDonor {
	use MailingListTrait;
	use ReceiptTrait;

	public function __construct(
		ScrubbedName $name,
		private readonly DonorType $originalDonorType,
		bool $mailingListOptIn,
		bool $requireReceipt
	) {
		$this->name = $name;
		$this->physicalAddress = new ScrubbedAddress();
		$this->emailAddress = '';

		if ( $mailingListOptIn ) {
			$this->subscribeToMailingList();
		} else {
			$this->unsubscribeFromMailingList();
		}

		if ( $requireReceipt ) {
			$this->requireReceipt();
		} else {
			$this->declineReceipt();
		}
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
