<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

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

}
