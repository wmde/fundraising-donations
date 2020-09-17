<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 */
interface Donor {

	public function getName(): DonorName;

	public function getPhysicalAddress(): Address;

	public function getEmailAddress(): string;

	public function isPrivatePerson(): bool;

	public function isCompany(): bool;
}
