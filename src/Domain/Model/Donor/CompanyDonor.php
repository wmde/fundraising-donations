<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

class CompanyDonor extends AbstractDonor {

	public function __construct( CompanyName $name, PostalAddress $address, string $emailAddress ) {
		$this->name = $name;
		$this->physicalAddress = $address;
		$this->emailAddress = $emailAddress;
	}

	public function isPrivatePerson(): bool {
		return false;
	}

	public function isCompany(): bool {
		return true;
	}

	public function getDonorType(): string {
		return (string)DonorType::COMPANY();
	}
}
