<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Traversable;

/**
 * @implements \IteratorAggregate<int>
 */
class DonationPaymentIdCollection implements \IteratorAggregate {
	/**
	 * @var array<int,int>
	 */
	private array $donationToPaymentIds = [];

	public function addPaymentForDonation( int $paymentId, int $donationId ): void {
		$this->donationToPaymentIds[$donationId] = $paymentId;
	}

	public function getIterator(): Traversable {
		return new \ArrayIterator( $this->donationToPaymentIds );
	}

	public function clear(): void {
		$this->donationToPaymentIds = [];
	}

	public function getPaymentIdForDonation( int $donationId ): ?int {
		return $this->donationToPaymentIds[$donationId] ?? null;
	}
}
