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

	private const MANUALLY_BOOKED_DONATIONS = [
		112974,
		331164,
		353933,
		323979,
		468736,
		543578,
	];

	private const ANONYMOUS_REFERENCE_CODE = 'AA-AAA-AAA-A';
	private const CHUNK_SIZE = 2000;
	public const CONVERT_ALL = -1;

	private PaymentIDRepository $dummyIdGeneratorForFollowups;
	private PaymentReferenceCode $anonymisedPaymentReferenceCode;
	private ConversionResult $result;

	/**
	 * In 2015 we had an error in the old fundraising app where we lost booking data.
	 * We'll "fake" the booking data to still mark the payments as booked
	 *
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $lostBookingDataPeriodStart;
	private \DateTimeImmutable $lostBookingDataPeriodEnd;

	public function __construct(
		private Connection $db,
		private PaymentIDRepository $idGenerator,
	) {
		$this->dummyIdGeneratorForFollowups = new NullGenerator();
		$this->anonymisedPaymentReferenceCode = PaymentReferenceCode::newFromString( self::ANONYMOUS_REFERENCE_CODE );
		$this->lostBookingDataPeriodStart = new \DateTimeImmutable( '2015-09-28 0:00:00' );
		$this->lostBookingDataPeriodEnd = new \DateTimeImmutable( '2015-10-08 0:00:00' );
	}

	/**
	 * Convert donations to payments
	 *
	 * Leave out parameters to convert all donations
	 *
	 * @param int $idOffset Starting donation ID
	 * @param int $maxConversions Maximum number of donations to convert
	 * @return ConversionResult
	 */
	public function convertDonations( int $idOffset = 0, int $maxConversions = self::CONVERT_ALL ): ConversionResult {
		$this->result = new ConversionResult();
		foreach ( $this->getRows( $idOffset, $maxConversions ) as $row ) {
			$this->result->addRow();
			if ( $row['data'] ) {
				$row['data'] = unserialize( base64_decode( $row['data'] ) );
			}

			// Skip payments
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

	 private function getRows( int $idOffset, int $maxDonationId ): iterable {
		if ( $maxDonationId === self::CONVERT_ALL ) {
			$maxDonationId = $this->getMaxId();
		}
		$qb = $this->db->createQueryBuilder();
		$qb->select( 'd.id', 'betrag AS amount', 'periode AS intervalInMonths', 'zahlweise AS paymentType',
			 'ueb_code AS transferCode', 'data', 'status', 'dt_new AS donationDate', 'ps.confirmed_at AS valuationDate',
		)
			 ->from( 'spenden', 'd' )
			 ->leftJoin( 'd', 'donation_payment', 'p', 'd.payment_id = p.id' )
			 ->leftJoin( 'p', 'donation_payment_sofort', 'ps', 'ps.id = p.id' );

		return new ChunkedQueryResultIterator( $qb, 'd.id', self::CHUNK_SIZE, $maxDonationId, $idOffset );
	 }

	private function getMaxId(): int {
		$maxId = $this->db->executeQuery( "SELECT MAX(id) FROM spenden" )->fetchOne();
		if ( $maxId === false ) {
			throw new \RuntimeException( 'Could not get maximum ID' );
		}
		return $maxId;
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
			$this->idGenerator->getNewID(),
			$this->getAmount( $row ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) )
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE || $row['status'] === Donation::STATUS_CANCELLED ) {
			return $payment;
		}
		if ( empty( $row['data']['ext_payment_id'] ) ) {
			$donationDate = new \DateTimeImmutable( $row['donationDate'] );
			if ( in_array( $row['id'], self::MANUALLY_BOOKED_DONATIONS ) ) {
				$row['data']['ext_payment_id'] = 'unknown, manually booked';
				$this->result->addWarning( 'Booked Credit card without transaction ID, booked by admins', $row );
			} elseif ( $donationDate >= $this->lostBookingDataPeriodStart && $donationDate <= $this->lostBookingDataPeriodEnd ) {
				$this->result->addWarning( 'Booked Credit card without transaction ID, 2015 error period', $row );
				$row['data']['ext_payment_id'] = 'unknown, manually booked';
			}
		}

		$payment->bookPayment( $this->getBookingData( self::MCP_LEGACY_KEY_MAP, $row['data'] ), $this->dummyIdGeneratorForFollowups );
		return $payment;
	}

	private function newPayPalPayment( array $row ): PayPalPayment {
		$payment = new PayPalPayment(
			$this->idGenerator->getNewID(),
			$this->getAmount( $row ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) )
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE || $row['status'] === Donation::STATUS_CANCELLED ) {
			return $payment;
		}
		if ( empty( $row['data']['paypal_payer_id'] ) ) {
			$donationDate = new \DateTimeImmutable( $row['donationDate'] );
			if ( in_array( $row['id'], self::MANUALLY_BOOKED_DONATIONS ) ) {
				$this->result->addWarning( 'Booked Paypal without payer ID, booked by admins', $row );
				$row['data']['paypal_payer_id'] = 'unknown, manually booked';
			} elseif ( $donationDate >= $this->lostBookingDataPeriodStart && $donationDate <= $this->lostBookingDataPeriodEnd ) {
				$this->result->addWarning( 'Booked Paypal without payer ID, 2015 error period', $row );
				$row['data']['paypal_payer_id'] = 'unknown, manually booked';
			}
		}
		if ( empty( $row['data']['ext_payment_timestamp'] ) ) {
			$log = $row['data']['log'] ?? [];
			foreach ( $log as $date => $logmsg ) {
				if ( $logmsg === 'paypal_handler: booked' ) {
					$row['data']['ext_payment_timestamp'] = $date;
					$this->result->addWarning( 'Booked Paypal payment without timestamp, restored from log', $row );
					break;
				}
			}
			if ( empty( $row['data']['ext_payment_timestamp'] ) ) {
				$this->result->addWarning( 'Booked Paypal payment without timestamp, assumed donation date', $row );
				$row['data']['ext_payment_timestamp'] = $row['donationDate'];
			}
		} elseif ( !\DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $row['data']['ext_payment_timestamp'] ) ) {
			$solution = 'created from donation date';
			try {

				$bookingDate = new \DateTimeImmutable( $row['data']['ext_payment_timestamp'] );
				$row['data']['ext_payment_timestamp'] = $bookingDate->format( 'Y-m-d H:i:s' );
				$solution = 'reformatted existing date';
			} catch ( \Exception ) {
				$row['data']['ext_payment_timestamp'] = $row['donationDate'];
			}
			$this->result->addWarning( 'Invalid date format for booked PayPal, ' . $solution, $row );
		}
		// TODO convert followup payments by looking at log message '/new transaction id to corresponding parent donation: (\d+)/'
		$payment->bookPayment( $this->getBookingData( self::PPL_LEGACY_KEY_MAP, $row['data'] ), $this->dummyIdGeneratorForFollowups );
		return $payment;
	}

	private function newSofortPayment( array $row ): SofortPayment {
		$interval = PaymentInterval::from( intval( $row['intervalInMonths'] ) );
		if ( $interval !== PaymentInterval::OneTime ) {
			$this->result->addWarning( 'Recurring interval for sofort payment, set to one-time', [ ...$row, 'interval' => $interval->value ] );
			$interval = PaymentInterval::OneTime;
		}
		$payment = SofortPayment::create(
			$this->idGenerator->getNewID(),
			$this->getAmount( $row ),
			$interval,
			$this->getPaymentReferenceCode( $row )
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE || $row['status'] === Donation::STATUS_CANCELLED ) {
			return $payment;
		}
		if ( empty( $row['valuationDate'] ) ) {
			$row['valuationDate'] = ( new \DateTimeImmutable( $row['donationDate'] ) )->format( DATE_ATOM );
			$this->result->addWarning( 'Sofort donation with empty valuation date, using donation date', $row );
		}
		$bookingData = [
			// We fake the transaction id, we did not store it previously
			'transactionId' => md5( 'sofort-' . $row['id'] ),
			'valuationDate' => ( new \DateTimeImmutable( $row['valuationDate'] ) )->format( DATE_ATOM )
		];
		$payment->bookPayment( $bookingData, $this->dummyIdGeneratorForFollowups );
		if ( $payment->getPaymentReferenceCode() === self::ANONYMOUS_REFERENCE_CODE ) {
			$payment->anonymise();
		}
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
			$this->idGenerator->getNewID(),
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
			$this->idGenerator->getNewID(),
			$this->getAmount( $row ),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) ),
			$paymentReferenceCode
		);
		if ( $payment->getPaymentReferenceCode() === self::ANONYMOUS_REFERENCE_CODE ) {
			$payment->anonymise();
		}
		if ( $row['status'] == Donation::STATUS_CANCELLED ) {
			$payment->cancel();
		}
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
