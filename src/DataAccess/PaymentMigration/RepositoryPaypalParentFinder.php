<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\PaymentContext\DataAccess\DoctrinePaymentRepository;
use WMDE\Fundraising\PaymentContext\Domain\Exception\PaymentNotFoundException;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;

class RepositoryPaypalParentFinder implements PaypalParentFinder {

	private DoctrinePaymentRepository $repo;

	public function __construct( EntityManager $entityManager, private PaymentIdFinder $paymentIdFinder ) {
		$this->repo = new DoctrinePaymentRepository( $entityManager );
	}

	/**
	 * @param array<string,mixed> $row
	 * @param ConversionResult $result
	 *
	 * @return PayPalPayment|null
	 */
	public function getParentPaypalPayment( array $row, ConversionResult $result ): ?PayPalPayment {
		$log = $row['data']['log'] ?? [];
		foreach ( $log as $message ) {
			if ( preg_match( '/new transaction id (?:to )corresponding parent donation: (\d+)/', $message, $matches ) ) {
				try {
					$parentDonationPaymentId = $this->paymentIdFinder->findPaymentIdForDonation( intval( $matches[1] ) );
				} catch ( \RuntimeException $e ) {
					$result->addWarning( "Followup Payment: Could not find payment ID for donation", $row );
					return null;
				}
				try {
					$payment = $this->repo->getPaymentById( $parentDonationPaymentId );
				} catch ( PaymentNotFoundException ) {
					throw new \RuntimeException( sprintf( "Donation %d contained reference to parent donation %d with payment id %d. Payment was not found,", $row['id'], $matches[1], $parentDonationPaymentId ) );
				}

				if ( $payment instanceof PayPalPayment ) {
					return $payment;
				}

				$result->addError( 'Got non-paypal payment', $row );
				return null;
			}
		}
		return null;
	}
}
