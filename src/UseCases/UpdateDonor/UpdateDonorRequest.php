<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

/**
 * @license GPL-2.0-or-later
 */
class UpdateDonorRequest {

	private int $donationId = 0;
	private DonorType $donorType;
	private string $firstName = '';
	private string $lastName = '';
	private string $salutation = '';
	private string $title = '';
	private string $companyName = '';
	private string $streetAddress = '';
	private string $postalCode = '';
	private string $city = '';
	private string $countryCode = '';
	private string $emailAddress = '';

	public function __construct() {
		$this->donorType = DonorType::PERSON();
	}

	public static function newInstance(): self {
		return new self();
	}

	public function withType( DonorType $donorType ): self {
		$request = clone $this;
		$request->donorType = $donorType;
		return $request;
	}

	public function withFirstName( string $firstName ): self {
		$request = clone $this;
		$request->firstName = trim( $firstName );
		return $request;
	}

	public function withLastName( string $lastName ): self {
		$request = clone $this;
		$request->lastName = trim( $lastName );
		return $request;
	}

	public function withSalutation( string $salutation ): self {
		$request = clone $this;
		$request->salutation = trim( $salutation );
		return $request;
	}

	public function withTitle( string $title ): self {
		$request = clone $this;
		$request->title = trim( $title );
		return $request;
	}

	public function withCompanyName( string $companyName ): self {
		$request = clone $this;
		$request->companyName = trim( $companyName );
		return $request;
	}

	public function withStreetAddress( string $streetAddress ): self {
		$request = clone $this;
		$request->streetAddress = trim( $streetAddress );
		return $request;
	}

	public function withPostalCode( string $postalCode ): self {
		$request = clone $this;
		$request->postalCode = trim( $postalCode );
		return $request;
	}

	public function withCity( string $city ): self {
		$request = clone $this;
		$request->city = trim( $city );
		return $request;
	}

	public function withCountryCode( string $countryCode ): self {
		$request = clone $this;
		$request->countryCode = trim( $countryCode );
		return $request;
	}

	public function withEmailAddress( string $emailAddress ): self {
		$request = clone $this;
		$request->emailAddress = trim( $emailAddress );
		return $request;
	}

	public function withDonationId( int $donationId ): self {
		$request = clone $this;
		$request->donationId = $donationId;
		return $request;
	}

	public function getDonorType(): DonorType {
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
