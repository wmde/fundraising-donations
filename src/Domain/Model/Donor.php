<?php

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
interface Donor {

	public function getName(): LegacyDonorName;

	public function getPhysicalAddress(): LegacyDonorAddress;

	public function getEmailAddress(): string;
}