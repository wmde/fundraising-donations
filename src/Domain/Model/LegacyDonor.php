<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 */
class LegacyDonor implements Donor {

	private DonorName $name;
	private $physicalAddress;
	private $emailAddress;

	public function __construct( DonorName $name, LegacyDonorAddress $address, string $emailAddress ) {
		$this->name = $name;
		$this->physicalAddress = $address;
		$this->emailAddress = $emailAddress;
	}

	public function getName(): DonorName {
		return $this->name;
	}

	public function getPhysicalAddress(): LegacyDonorAddress {
		return $this->physicalAddress;
	}

	public function getEmailAddress(): string {
		return $this->emailAddress;
	}

	public function isPrivatePerson(): bool {
		return $this->name instanceof PersonName;
	}

	public function isCompany(): bool {
		return $this->name instanceof CompanyName;
	}

}
