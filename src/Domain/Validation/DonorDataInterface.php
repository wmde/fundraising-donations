<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Validation;

interface DonorDataInterface {

	public function getDonorType(): string;

	public function getFirstName(): string;

	public function getLastName(): string;

	public function getSalutation(): string;

	public function getTitle(): string;

	public function getCompanyName(): string;

	public function getStreetAddress(): string;

	public function getPostalCode(): string;

	public function getCity(): string;

	public function getCountryCode(): string;

	public function getEmailAddress(): string;
}
