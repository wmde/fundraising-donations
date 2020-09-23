<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\NoAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

class EmailDonor extends AbstractDonor {

	public function __construct( PersonName $name, string $emailAddress ) {
		$this->name = $name;
		$this->emailAddress = $emailAddress;
		$this->physicalAddress = new NoAddress();
	}

	public function isPrivatePerson(): bool {
		return true;
	}

	public function isCompany(): bool {
		return false;
	}

	public function getDonorType(): string {
		return (string)DonorType::EMAIL();
	}

}
