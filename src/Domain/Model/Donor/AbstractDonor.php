<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

use WMDE\Fundraising\DonationContext\Domain\Model\Address;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;

abstract class AbstractDonor implements Donor {

	protected DonorName $name;
	protected Address $physicalAddress;
	protected string $emailAddress;

	public function getName(): DonorName {
		return $this->name;
	}

	public function getPhysicalAddress(): Address {
		return $this->physicalAddress;
	}

	public function getEmailAddress(): string {
		return $this->emailAddress;
	}

	public function hasEmailAddress(): bool {
		return !empty( $this->getEmailAddress() );
	}

}
