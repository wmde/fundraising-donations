<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

interface DonorName {

	public function getFullName(): string;

	/**
	 * @return string[]
	 */
	public function toArray(): array;

}
