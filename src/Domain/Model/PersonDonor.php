<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

class PersonDonor extends AbstractDonor {

	public function __construct( PersonName $name, PostalAddress $address, string $emailAddress ) {
		$this->name = $name;
		$this->physicalAddress = $address;
		$this->emailAddress = $emailAddress;
	}

	public function isPrivatePerson(): bool {
		return true;
	}

	public function isCompany(): bool {
		return false;
	}

}
