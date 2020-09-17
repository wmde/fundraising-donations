<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Event;

use WMDE\Fundraising\DonationContext\Domain\Event;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;

class DonationCreatedEvent implements Event {
	private int $donationId;
	private ?Donor $donor;

	public function __construct( int $donationId, ?Donor $donor ) {
		$this->donationId = $donationId;
		$this->donor = $donor;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function getDonor(): ?Donor {
		return $this->donor;
	}
}
