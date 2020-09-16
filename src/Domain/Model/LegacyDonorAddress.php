<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 */
class LegacyDonorAddress implements Address {

	private string $streetAddress = '';
	private string $postalCode = '';
	private string $city = '';
	private string $countryCode = '';

	public function __construct( string $streetAddress, string $postalCode, string $city, string $countryCode ) {
		$this->streetAddress = $streetAddress;
		$this->postalCode = $postalCode;
		$this->city = $city;
		$this->countryCode = $countryCode;
	}

	public function getStreetAddress(): string {
		return $this->streetAddress;
	}

	public function getPostalCode(): string {
		return $this->postalCode;
	}

	public function getCity(): string {
		return $this->city;
	}

	public function getCountryCode(): string {
		return $this->countryCode;
	}

}
