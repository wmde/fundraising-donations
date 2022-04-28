<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Doctrine\DBAL\Connection;
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

	private PaymentReferenceCode $anonymisedPaymentReferenceCode;
	private AnalysisResult $result;

	private const ANONYMOUS_REFERENCE_CODE = 'AA-AAA-AAA-A';

	public function __construct(
		private Connection $db
	) {
		$this->idGenerator = new NullGenerator();
		$this->anonymisedPaymentReferenceCode = PaymentReferenceCode::newFromString( self::ANONYMOUS_REFERENCE_CODE );
	}

	public function convertDonations(): AnalysisResult {
		/** @var \PDO $connection */
		$connection = $this->db->getNativeConnection();
		$connection->setAttribute( \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false );
		$qb = $this->db->createQueryBuilder();
		$qb->select( 'd.id', 'betrag AS amount', 'periode AS intervalInMonths', 'zahlweise AS paymentType',
			'ueb_code AS transferCode', 'data', 'status', 'dt_new AS donationDate', 'ps.confirmed_at AS valuationDate',
			'p.id AS paymentId' )
			->from( 'spenden', 'd' )
			->leftJoin( 'd', 'donation_payment', 'p', 'd.payment_id = p.id' )
			->leftJoin( 'p', 'donation_payment_sofort', 'ps', 'ps.id = p.id' )
			->setMaxResults( 100000 );

		$dbResult = $qb->executeQuery();
		$this->result = new AnalysisResult();
		foreach ( $dbResult->iterateAssociative() as $row ) {
			$this->result->addRow();
			if ( $row['data'] ) {
				$row['data'] = unserialize( base64_decode( $row['data'] ) );
			}

			// Skip payments
			if ( $row['paymentType'] === 'PPL' && $row['status'] === 'D' ) {
				$this->result->addWarning( 'Skipped deleted paypal payment', $row );
				continue;
			}

			if ( $row['paymentType'] === 'MBK' ) {
				$this->result->addWarning( 'Skipped MBK payment', $row );
				continue;
			}

			try {
				$payment = $this->newPayment( $row );
				// We might actually save the payment here later
			} catch ( \Throwable $e ) {
				$msg = $e->getMessage();
				$this->result->addError( $msg, $row );
			}
		}
		return $this->result;
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
				throw new \Exception( sprintf( "Unknown payment type '%s'", $row['paymentType'] ) );
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
			$this->getAmount( $row ),
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
			$this->getAmount( $row ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) )
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE ) {
			return $payment;
		}
		if ( empty( $row['data']['ext_payment_timestamp'] ) ) {
			$this->result->addWarning( 'Paypal payment without timestamp', $row );
			$row['data']['ext_payment_timestamp'] = $row['donationDate'];
		}
		// TODO convert followup payments, probably using reflection
		$payment->bookPayment( $this->getBookingData( self::PPL_LEGACY_KEY_MAP, $row['data'] ), $this->idGenerator );
		return $payment;
	}

	private function newSofortPayment( array $row ): SofortPayment {
		$paymentReferenceCode = empty( $row['transferCode'] ) ? null : PaymentReferenceCode::newFromString( $row['transferCode'] );
		$interval = PaymentInterval::from( intval( $row['intervalInMonths'] ) );
		if ( $interval !== PaymentInterval::OneTime ) {
			$this->result->addWarning( 'Recurring interval for sofort payment', [ ...$row, 'interval' => $interval->value ] );
			$interval = PaymentInterval::OneTime;
		}
		$payment = SofortPayment::create(
			intval( $row['paymentId'] ),
			$this->getAmount( $row ),
			$interval,
			$paymentReferenceCode
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE ) {
			return $payment;
		}
		if ( empty( $row['valuationDate'] ) ) {
			throw new \Exception( 'Valuation date missing from sofort donation' );
		}
		$bookingData = [
			// We fake the transaction id, we did not store it previously
			'transactionId' => md5( 'sofort-' . $row['id'] ),
			'valuationDate' => ( new \DateTimeImmutable( $row['valuationDate'] ) )->format( DATE_ATOM )
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
			$this->getAmount( $row ),
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
		$paymentReferenceCode = $this->getPaymentReferenceCode( $row );
		$payment = BankTransferPayment::create(
			intval( $row['paymentId'] ),
			$this->getAmount( $row ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) ),
			$paymentReferenceCode
		);
		if ( $payment->getPaymentReferenceCode() === self::ANONYMOUS_REFERENCE_CODE ) {
			$payment->anonymise();
		}
		// TODO uncomment when https://github.com/wmde/fundraising-payments/pull/95 is merged
		//if ( $row['status'] == Donation::STATUS_CANCELLED ) {
		//	$payment->cancel();
		//}
		return $payment;
	}

	private function getPaymentReferenceCode( array $row ): PaymentReferenceCode {
		if ( empty( $row['transferCode'] ) ) {
			return $this->anonymisedPaymentReferenceCode;
		}
		if ( !preg_match( '/^\w{2}-\w{3}-\w{3}-\w$/', $row['transferCode'] ) ) {
			$this->result->addWarning( 'Legacy transfer code pattern, omitting', $row );
			return $this->anonymisedPaymentReferenceCode;
		}
		return PaymentReferenceCode::newFromString( $row['transferCode'] );
	}

	private function getAmount( array $row ): Euro {
		$amount = $row['amount'];
		if ( $amount === '' || $amount === null ) {
			$this->result->addWarning( 'Converted empty amount to 0', $row );
			$amount = '0';
		}
		return Euro::newFromString( $amount );
	}

}
