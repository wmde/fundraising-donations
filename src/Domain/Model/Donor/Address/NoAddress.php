<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address;

use WMDE\Fundraising\DonationContext\Domain\Model\Address;

/**
 * @license GPL-2.0-or-later
 */
class NoAddress implements Address {

	public function getStreetAddress(): string {
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
