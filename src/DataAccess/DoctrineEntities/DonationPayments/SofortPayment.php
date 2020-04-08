<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayment;

/**
 * @ORM\Table(name="donation_payment_sofort")
 * @ORM\Entity
 */
class SofortPayment extends DonationPayment {

	/**
	 * @var DateTime|null
	 *
	 * @ORM\Column(name="confirmed_at", type="datetime", nullable=true)
	 */
	private $confirmedAt;

	public function getConfirmedAt(): ?DateTime {
		return $this->confirmedAt;
	}

	public function setConfirmedAt( ?DateTime $confirmedAt ): void {
		$this->confirmedAt = $confirmedAt;
	}
}
