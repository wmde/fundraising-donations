<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Event;

use WMDE\Fundraising\DonationContext\Domain\Event;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;

class DonorUpdatedEvent implements Event {

	private int $donationId;
	private Donor $previousDonor;
	private Donor $newDonor;

	public function __construct( int $donationId, Donor $previousDonor, Donor $newDonor ) {
		$this->donationId = $donationId;
		$this->previousDonor = $previousDonor;
		$this->newDonor = $newDonor;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function getPreviousDonor(): Donor {
		return $this->previousDonor;
	}

	public function getNewDonor(): Donor {
		return $this->newDonor;
	}
}
