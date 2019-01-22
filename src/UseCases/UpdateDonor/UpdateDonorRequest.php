<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

/**
 * @license GNU GPL v2+
 */
class UpdateDonorRequest {

	private $donationId;
	private $donorType;
	private $firstName;
	private $lastName;
	private $salutation;
	private $title;
	private $companyName;
	private $streetAddress;
	private $postalCode;
	private $city;
	private $countryCode;
	private $emailAddress;

	public static function newInstance(): self {
		return new self();
	}

	public function withType( string $donorType ): self {
		$request = clone $this;
		$request->donorType = $donorType;
		return $request;
	}

	public function withFirstName( string $firstName ): self {
		$request = clone $this;
		$request->firstName = $firstName;
		return $request;
	}

	public function withLastName( string $lastName ): self {
		$request = clone $this;
		$request->lastName = $lastName;
		return $request;
	}

	public function withSalutation( string $salutation ): self {
		$request = clone $this;
		$request->salutation = $salutation;
		return $request;
	}

	public function withTitle( string $title ): self {
		$request = clone $this;
		$request->title = $title;
		return $request;
	}

	public function withCompanyName( string $companyName ): self {
		$request = clone $this;
		$request->companyName = $companyName;
		return $request;
	}

	public function withStreetAddress( string $streetAddress ): self {
		$request = clone $this;
		$request->streetAddress = $streetAddress;
		return $request;
	}

	public function withPostalCode( string $postalCode ): self {
		$request = clone $this;
		$request->postalCode = $postalCode;
		return $request;
	}

	public function withCity( string $city ): self {
		$request = clone $this;
		$request->city = $city;
		return $request;
	}

	public function withCountryCode( string $countryCode ): self {
		$request = clone $this;
		$request->countryCode = $countryCode;
		return $request;
	}

	public function withEmailAddress( string $emailAddress ): self {
		$request = clone $this;
		$request->emailAddress = $emailAddress;
		return $request;
	}

	public function withDonationId( int $donationId ): self {
		$request = clone $this;
		$request->donationId = $donationId;
		return $request;
	}

	public function getDonorType(): string {
		return $this->donorType;
	}

	public function getFirstName(): string {
		return $this->firstName;
	}

	public function getLastName(): string {
		return $this->lastName;
	}

	public function getSalutation(): string {
		return $this->salutation;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function getCompanyName(): string {
		return $this->companyName;
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

	public function getEmailAddress(): string {
		return $this->emailAddress;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}
}