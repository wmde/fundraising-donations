<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities;

class DonationTracking {

	// @phpstan-ignore property.unused (Doctrine will initialize this ID)
	private ?int $id;

	public function __construct(
		public readonly string $campaign = '',
		public readonly string $keyword = ''
	) {
	}

	public function getTracking(): string {
		return $this->campaign . '/' . $this->keyword;
	}
}
