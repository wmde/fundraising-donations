<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use WMDE\Fundraising\PaymentContext\Domain\PaymentIdRepository;

class NullGenerator implements PaymentIDRepository {
	public function getNewID(): int {
		throw new \LogicException( 'ID generator is only for followup payments, this should not happen' );
	}
}
