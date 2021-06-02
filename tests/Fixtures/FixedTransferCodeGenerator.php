<?php
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\PaymentContext\Domain\TransferCodeGenerator;

class FixedTransferCodeGenerator implements TransferCodeGenerator {
	public const DEFAULT = 'ZZ9 Plural Z Alpha';

	private string $transferCode;

	public function __construct( string $transferCode = self::DEFAULT ) {
		$this->transferCode = $transferCode;
	}

	public function generateTransferCode( string $prefix ): string {
		return $prefix . $this->transferCode;
	}

}
