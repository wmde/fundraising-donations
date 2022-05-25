<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;

class PaymentIdFinder {
	private Statement $getPaymentIdStatement;

	public function __construct(
		Connection $connection,
		private readonly DonationPaymentIdCollection $donationPaymentIdCollection ) {
		$this->getPaymentIdStatement = $connection->prepare( 'SELECT payment_id FROM spenden WHERE id=?' );
	}

	public function findPaymentIdForDonation( int $donationId ): int {
		$paymentId = $this->donationPaymentIdCollection->getPaymentIdForDonation( $donationId ) ?? $this->getPaymentIdFromDb( $donationId );
		if ( $paymentId === null ) {
			throw new \RuntimeException( "No Payment id found for donation with ID $donationId" );
		}
		return $paymentId;
	}

	private function getPaymentIdFromDb( int $donationId ): ?int {
		$paymentId = $this->getPaymentIdStatement->executeQuery( [ $donationId ] )->fetchOne();
		if ( $paymentId === false ) {
			return null;
		}
		return intval( $paymentId );
	}

}
