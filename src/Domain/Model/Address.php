<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 */
interface Address {

	public function getStreetAddress(): string;

	public function getPostalCode(): string;

	public function getCity(): string;

	public function getCountryCode(): string;
}
