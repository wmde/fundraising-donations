<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;

class ScrubbedName implements DonorName {

	public function __construct( private readonly string $salutation ) {
	}

	public function getFullName(): string {
		return '';
	}

	public function toArray(): array {
		return [
			'salutation' => $this->salutation,
		];
	}

	public function getSalutation(): string {
		return $this->salutation;
	}
}
