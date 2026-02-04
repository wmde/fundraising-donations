<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address;

use WMDE\Fundraising\DonationContext\Domain\Model\Address;

class NoAddress implements Address {

	/**
	 * @deprecated Use getStreetName() and getHouseNumber() instead.
	 */
	public function getStreetAddress(): string {
		return '';
	}

	public function getStreetName(): string {
		return '';
	}

	public function getHouseNumber(): string {
		return '';
	}

	public function getPostalCode(): string {
		return '';
	}

	public function getCity(): string {
		return '';
	}

	public function getCountryCode(): string {
		return '';
	}

}
