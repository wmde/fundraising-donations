<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address;

use WMDE\Fundraising\DonationContext\Domain\Model\Address;

class PostalAddress implements Address {

	/**
	 * @deprecated Use $streetName and $houseNumber instead.
	 */
	private string $streetAddress = '';
	private string $streetName = '';
	private string $houseNumber = '';
	private string $postalCode = '';
	private string $city = '';
	private string $countryCode = '';

	private function __construct( string $streetAddress, string $postalCode, string $city, string $countryCode ) {
		$this->streetAddress = $streetAddress;
		$this->postalCode = $postalCode;
		$this->city = $city;
		$this->countryCode = $countryCode;
	}

	public static function fromStreetNameAndHouseNumber(
		string $streetName,
		string $houseNumber,
		string $postalCode,
		string $city,
		string $countryCode
	): self {
		$postalAddress = new self( $streetName . ' ' . $houseNumber, $postalCode, $city, $countryCode );
		$postalAddress->streetName = $streetName;
		$postalAddress->houseNumber = $houseNumber;
		return $postalAddress;
	}

	public static function fromLegacyStreetName(
		string $legacyStreetAddress,
		string $postalCode,
		string $city,
		string $countryCode
	): self {
		$postalAddress = new self( $legacyStreetAddress, $postalCode, $city, $countryCode );
		$postalAddress->streetName = '';
		$postalAddress->houseNumber = '';
		return $postalAddress;
	}

	public function getStreetAddress(): string {
		if ( trim( $this->streetAddress ) !== '' ) {
			return $this->streetAddress;
		}
		return trim( $this->getStreetName() . ' ' . $this->getHouseNumber() );
	}

	public function getStreetName(): string {
		return $this->streetName;
	}

	public function getHouseNumber(): string {
		return $this->houseNumber;
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
