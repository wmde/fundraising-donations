<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

interface Address {

	public function getStreetAddress(): string;

	public function getPostalCode(): string;

	public function getCity(): string;

	public function getCountryCode(): string;
}
