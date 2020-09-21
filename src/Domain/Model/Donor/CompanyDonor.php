<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;

class CompanyDonor extends AbstractDonor {

	private const DONOR_TYPE = 'company';

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
		return self::DONOR_TYPE;
	}
}
