<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class LegacyDonor implements Donor {

	private $personName;
	private $physicalAddress;
	private $emailAddress;

	public function __construct( LegacyDonorName $name, LegacyDonorAddress $address, string $emailAddress ) {
		$this->personName = $name;
		$this->physicalAddress = $address;
		$this->emailAddress = $emailAddress;
	}

	public function getName(): LegacyDonorName {
		return $this->personName;
	}

	public function getPhysicalAddress(): LegacyDonorAddress {
		return $this->physicalAddress;
	}

	public function getEmailAddress(): string {
		return $this->emailAddress;
	}

}
