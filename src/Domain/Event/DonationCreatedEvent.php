<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Event;

use WMDE\Fundraising\DonationContext\Domain\Event;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonor;

class DonationCreatedEvent implements Event {
	private int $donationId;
	private ?LegacyDonor $donor;

	public function __construct( int $donationId, ?LegacyDonor $donor ) {
		$this->donationId = $donationId;
		$this->donor = $donor;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function getDonor(): ?LegacyDonor {
		return $this->donor;
	}
}
