<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

interface Address {

	/**
	 * @deprecated Use getStreetName() and getHouseNumber() instead.
	 */
	public function getStreetAddress(): string;

	public function getStreetName(): string;

	public function getHouseNumber(): string;

	public function getPostalCode(): string;

	public function getCity(): string;

	public function getCountryCode(): string;
}
