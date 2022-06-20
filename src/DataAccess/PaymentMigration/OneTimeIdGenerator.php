<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use WMDE\Fundraising\PaymentContext\Domain\PaymentIdRepository;

class OneTimeIdGenerator implements PaymentIDRepository {
	private bool $idGenerated = false;

	public function __construct( private int $paymentId ) {
	}

	public function getNewID(): int {
		if ( $this->idGenerated ) {
			throw new \RuntimeException( 'Only one call to getId allowed' );
		}
		$this->idGenerated = true;
		return $this->paymentId;
	}

}
