<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

interface DonorName {

	public function getFullName(): string;

	/**
	 * Get name components for usage in templates
	 *
	 * @return array<string,string>
	 */
	public function toArray(): array;

}
