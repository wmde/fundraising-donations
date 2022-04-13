<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities;

/**
 * Abstract base class for DonationPayment subclasses
 *
 * @deprecated Use Payment class from payment domain instead
 */
abstract class DonationPayment {

	private int $id;

	public function getId(): int {
		return $this->id;
	}

	public function setId( int $id ): void {
		$this->id = $id;
	}

}
