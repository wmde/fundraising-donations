<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\NoAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\NoName;

class AnonymousDonor extends AbstractDonor {

	private const DONOR_TYPE = 'anonymous';

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
		return self::DONOR_TYPE;
	}

}
