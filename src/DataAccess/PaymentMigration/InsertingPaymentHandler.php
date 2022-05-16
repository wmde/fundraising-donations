<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

class InsertingPaymentHandler implements NewPaymentHandler {

	private const INSERT_BATCH_SIZE = 2000;

	private int $paymentIndex = 0;

	public function __construct( private readonly EntityManager $entityManager ) {
	}

	public function handlePayment( Payment $payment ): void {
		$this->entityManager->persist( $payment );
		$this->paymentIndex++;
		if ( ( $this->paymentIndex % self::INSERT_BATCH_SIZE ) === 0 ) {
			$this->entityManager->flush();
			$this->entityManager->clear();
		}
	}

	public function flushRemaining(): void {
		$this->entityManager->flush();
		$this->entityManager->clear();
	}

}
