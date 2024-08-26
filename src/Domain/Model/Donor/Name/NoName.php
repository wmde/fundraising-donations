<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;

/**
 * This is the name for anonymous donors.
 */
class NoName implements DonorName {

	public function getFullName(): string {
		return 'Anonym';
	}

	public function toArray(): array {
		return [];
	}

	public function getSalutation(): string {
		return '';
	}

}
