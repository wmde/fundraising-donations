<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

class InsertingPaymentHandler implements NewPaymentHandler {

	private const INSERT_BATCH_SIZE = 2000;

	private int $paymentIndex = 0;

	private array $paymentIDsOfDonations = [];

	private bool $transactionStarted = false;

	public function __construct( private readonly EntityManager $entityManager ) {
	}

	public function handlePayment( Payment $payment, int $donationId ): void {
		$this->startTransactionIfNeeded();
		$this->entityManager->persist( $payment );
		$this->paymentIDsOfDonations[$donationId] = $payment->getId();
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
		$this->entityManager->flush();
		$this->entityManager->clear();
		$this->commit();
		$this->startTransactionIfNeeded();

		$flushEnd = microtime( true );
		printf( "Took %2.5f seconds to commit payments\n", $flushEnd - $flushStart );
		$stmt = $this->entityManager->getConnection()->prepare( "UPDATE spenden SET payment_id=? WHERE id=?" );
		foreach ( $this->paymentIDsOfDonations as $donationId => $paymentId ) {
			$stmt->executeQuery( [ $paymentId, $donationId ] );
		}
		// Reset payment IDs for next batch
		$this->paymentIDsOfDonations = [];
		$this->commit();
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
