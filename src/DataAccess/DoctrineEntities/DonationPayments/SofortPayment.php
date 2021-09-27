<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments;

use DateTime;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayment;

class SofortPayment extends DonationPayment {

	private $confirmedAt;

	public function getConfirmedAt(): ?DateTime {
		return $this->confirmedAt;
	}

	public function setConfirmedAt( ?DateTime $confirmedAt ): void {
		$this->confirmedAt = $confirmedAt;
	}
}
