<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

class NoName implements DonorName {

	public function getFullName(): string {
		return 'Anonym';
	}

	public function toArray(): array {
		return [];
	}

}
