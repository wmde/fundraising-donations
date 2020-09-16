<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 */
interface DonorName {

	public function getFullName(): string;

	/**
	 * @return string[]
	 */
	public function toArray(): array;

}
