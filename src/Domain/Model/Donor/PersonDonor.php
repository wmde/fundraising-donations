<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

class PersonDonor extends AbstractDonor {
	use MailingListTrait;
	use ReceiptTrait;

	public function __construct( PersonName $name, PostalAddress $address, string $emailAddress ) {
		$this->name = $name;
		$this->physicalAddress = $address;
		$this->emailAddress = $emailAddress;

		// Server defaults for newsletter and receipt
		$this->unsubscribeFromMailingList();
		$this->requireReceipt();
	}

	public function isPrivatePerson(): bool {
		return true;
	}

	public function isCompany(): bool {
		return false;
	}

	public function getDonorType(): DonorType {
		return DonorType::PERSON;
	}
}
