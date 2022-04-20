<?php

// A script to test payment migration

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentReferenceCode;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Domain\Repositories\PaymentIDRepository;

$config = [
	'url' => 'mysql://fundraising:INSECURE PASSWORD@db/fundraising'
];

class NullGenerator implements PaymentIDRepository {
	public function getNewID(): int {
		throw new LogicException( 'ID generator is only for followup payments, this should not happen' );
	}
}

class DonationToPaymentConverter {
	private PaymentIDRepository $idGenerator;

	private const PPL_LEGACY_KEY_MAP = [
		'paypal_payer_id' => 'payer_id',
		'paypal_subscr_id' => 'subscr_id',
		'paypal_payer_status' => 'payer_status',
		'paypal_mc_gross' => 'mc_gross',
		'paypal_mc_currency' => 'mc_currency',
		'paypal_mc_fee' => 'mc_fee',
		'paypal_settle_amount' => 'settle_amount',
		'ext_payment_id' => 'txn_id',
		'ext_subscr_id' => 'subscr_id',
		'ext_payment_type' => 'payment_type',
		'ext_payment_status' => 'payment_status',
		'ext_payment_account' => 'payer_id',
		'ext_payment_timestamp' => 'payment_date',
	];

	private const MCP_LEGACY_KEY_MAP = [
		'ext_payment_id' => 'transactionId',
		'mcp_amount' => 'amount',
		'ext_payment_account' => 'customerId',
		'mcp_sessionid' => 'sessionId',
		'mcp_auth' => 'auth',
		'mcp_title' => 'title',
		'mcp_country' => 'country',
		'mcp_currency' => 'currency',
		'mcp_cc_expiry_date' => 'expiryDate'
	];

	public function __construct(
		private Connection $db
	) {
		$this->idGenerator = new NullGenerator();
	}

	public function convertDonations() {
		$qb = $this->db->createQueryBuilder();
		$qb->select( 'id', 'betrag AS amount', 'periode AS intervalInMonths', 'zahlweise as paymentType',
			'ueb_code as transferCode', 'data', 'status', 'ps.confirmed_at AS valuationDate', 'p.id as paymentId' )
			->from( 'spenden', 'd' )
			->leftJoin( 'd', 'donation_payments', 'p', 'd.payment_id = p.id' )
			->leftJoin( 'p', 'donation_payments_sofort', 'ps', 'ps.id = p.id' );

		$result = $qb->executeQuery();
		$processedPayments = 0;
		$errors = [];
		$errorTypes = [];
		foreach ( $result->iterateAssociative() as $row ) {
			if ( $row['data'] ) {
				$row['data'] = unserialize( base64_decode( $row['data'] ) );
			}
			try {
				$payment = $this->newPayment( $row );
				// We might actually save the payment here later
			} catch ( \Throwable $e ) {
				$msg = $e->getMessage();
				$errors[] = [ $row['id'], $msg, $row ];
				$errorTypes[$msg] = empty( $errorTypes[$msg] ) ? 1 : $errorTypes[$msg] + 1;
			}
			$processedPayments++;
		}
		return [ $processedPayments, $errors, $errorTypes ];
	}

	private function newPayment( array $row ): Payment {
		switch ( $row['paymentType'] ) {
			case 'PPL':
				return $this->newPayPalPayment( $row );
			case 'MCP':
				return $this->newCreditCardPayment( $row );
			case 'SUB':
				return $this->newSofortPayment( $row );
			case 'BEZ':
				return $this->newDirectDebitPayment( $row );
			case 'UEB':
				return $this->newBankTransferPayment( $row );
			default:
				throw new Exception( sprintf( "Unknown payment type '%s'", $row['paymentType'] ) );
		}
	}

	private function getBookingData( array $keymap, array $data ): array {
		$bookingData = [];
		foreach ( $keymap as $legacyKey => $name ) {
			if ( isset( $data[$legacyKey] ) ) {
				$bookingData[$name] = $data[$legacyKey];
			}
		}
		return $bookingData;
	}

	private function newCreditCardPayment( array $row ): CreditCardPayment {
		$payment = new CreditCardPayment(
			intval( $row['paymentId'] ),
			Euro::newFromString( $row['amount'] ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) )
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE ) {
			return $payment;
		}

		$payment->bookPayment( $this->getBookingData( self::MCP_LEGACY_KEY_MAP, $row['data'] ), $this->idGenerator );
		return $payment;
	}

	private function newPayPalPayment( array $row ): PayPalPayment {
		$payment = new PayPalPayment(
			intval( $row['paymentId'] ),
			Euro::newFromString( $row['amount'] ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) )
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE ) {
			return $payment;
		}
		// TODO convert followup payments, probably using reflection
		$payment->bookPayment( $this->getBookingData( self::PPL_LEGACY_KEY_MAP, $row['data'] ), $this->idGenerator );
		return $payment;
	}

	private function newSofortPayment( array $row ): SofortPayment {
		$paymentReferenceCode = empty( $row['transferCode'] ) ? null : PaymentReferenceCode::newFromString( $row['transferCode'] );
		$payment = SofortPayment::create(
			intval( $row['paymentId'] ),
			Euro::newFromString( $row['amount'] ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) ),
			$paymentReferenceCode
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE ) {
			return $payment;
		}
		if ( empty( $row['valuationDate'] ) ) {
			throw new Exception( 'Valuation date missing from sofort donation' );
		}
		$bookingData = [
			// We fake the transaction id, we did not store it previously
			'transactionId' => md5( 'sofort-' . $row['id'] ),
			'valuationDate' => ( new DateTimeImmutable( $row['valuationDate'] ) )->format( DATE_ATOM )
		];

		$payment->bookPayment( $bookingData, $this->idGenerator );
		return $payment;
	}

	private function newDirectDebitPayment( array $row ): DirectDebitPayment {
		if ( empty( $row['data']['iban'] ) ) {
			// DummyData
			$iban = new Iban( 'DE88100900001234567892' );
			$bic = 'BEVODEBB';
			$anonymous = true;
		} else {
			$iban = new Iban( $row['data']['iban'] );
			$bic = $row['data']['bic'] ?? '';
			$anonymous = false;
		}
		$payment = DirectDebitPayment::create(
			intval( $row['paymentId'] ),
			Euro::newFromString( $row['amount'] ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) ),
			$iban,
			$bic
		);
		if ( $anonymous ) {
			$payment->anonymise();
		}
		if ( $row['status'] == Donation::STATUS_CANCELLED ) {
			$payment->cancel();
		}
		return $payment;
	}

	private function newBankTransferPayment( array $row ): BankTransferPayment {
		$paymentReferenceCode = empty( $row['transferCode'] ) ? null : PaymentReferenceCode::newFromString( $row['transferCode'] );
		$payment = SofortPayment::create(
			intval( $row['paymentId'] ),
			Euro::newFromString( $row['amount'] ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) ),
			$paymentReferenceCode
		);
		// TODO uncomment when https://github.com/wmde/fundraising-payments/pull/95 is merged
		//if ( $row['status'] == Donation::STATUS_CANCELLED ) {
		//	$payment->cancel();
		//}
		return $payment;
	}

}

$db = DriverManager::getConnection( $config );
$converter = new DonationToPaymentConverter( $db );
[ $processedPayments, $errors, $errorTypes ] = $converter->convertDonations();

$errorCount = count( $errors );
printf( "Processed %d donations, with %d errors (%.2f%%)\n", $processedPayments, $errorCount, ( $errorCount * 100 ) / $processedPayments );
