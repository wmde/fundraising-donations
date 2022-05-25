<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

class InsertingPaymentHandler implements NewPaymentHandler {

	private const INSERT_BATCH_SIZE = 2000;

	private int $paymentIndex = 0;

	private bool $transactionStarted = false;

	public function __construct(
		private readonly EntityManager $entityManager,
		private readonly DonationPaymentIdCollection $paymentIdCollection ) {
	}

	public function handlePayment( Payment $payment, int $donationId ): void {
		$this->entityManager->persist( $payment );
		$this->paymentIdCollection->addPaymentForDonation( $payment->getId(), $donationId );
		$this->paymentIndex++;
		if ( ( $this->paymentIndex % self::INSERT_BATCH_SIZE ) === 0 ) {
			$this->flush();
		}
	}

	public function flushRemaining(): void {
		$this->flush();
	}

	private function flush(): void {
		$flushStart = microtime( true );
		try {
			$this->entityManager->flush();
		} catch ( \Exception $e ) {
			// We prevent the exception from being caught upstream, because we don't want to count these errors:
			// The exception will be some kind of unrecoverable database error.
			die( $e->getMessage() );
		}
		$this->entityManager->clear();
		$flushEnd = microtime( true );

		$this->startTransactionIfNeeded();
		printf( "Took %2.5f seconds to commit payments\n", $flushEnd - $flushStart );
		$stmt = $this->entityManager->getConnection()->prepare( "UPDATE spenden SET payment_id=? WHERE id=?" );
		foreach ( $this->paymentIdCollection as $donationId => $paymentId ) {
			$stmt->executeQuery( [ $paymentId, $donationId ] );
		}
		$this->commit();
		$this->paymentIdCollection->clear();
		$updateEnd = microtime( true );
		printf( "Took %2.5f seconds to update donations\n", $updateEnd - $flushEnd );
	}

	private function startTransactionIfNeeded(): void {
		if ( $this->transactionStarted ) {
			return;
		}
		$this->entityManager->beginTransaction();
		$this->transactionStarted = true;
	}

	private function commit() {
		if ( !$this->transactionStarted ) {
			return;
		}
		$this->entityManager->commit();
		$this->transactionStarted = false;
	}

}
