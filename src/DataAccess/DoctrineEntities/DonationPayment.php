<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities;

use Doctrine\ORM\Mapping as ORM;

/**
 * Abstract base class for DonationPayment subclasses
 *
 * @ORM\Entity
 * @ORM\Table(name="donation_payment")
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="payment_type", type="string", length=3)
 * @ORM\DiscriminatorMap({"SUB" = "WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments\SofortPayment"})
 */
abstract class DonationPayment {

	/**
	 * @var int
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 * @ORM\Id
	 */
	private $id;

	public function getId(): int {
		return $this->id;
	}

	public function setId( int $id ): void {
		$this->id = $id;
	}

}
