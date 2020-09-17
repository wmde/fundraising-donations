<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 */
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

}
