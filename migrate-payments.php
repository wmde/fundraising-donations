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

require __DIR__ . '/vendor/autoload.php';

$config = [
	'url' => 'mysql://fundraising:INSECURE PASSWORD@database/fundraising'
];

class NullGenerator implements PaymentIDRepository {
	public function getNewID(): int {
		throw new LogicException( 'ID generator is only for followup payments, this should not happen' );
	}
}

class ResultObject {
	private array $itemBuffer = [];
	private array $itemCounts = [];
	private array $currentItemTypeIndex = [];

	public function __construct( private int $bufferSize)
	{
	}

	public function add(string $itemType, array $row ): void {
		// TODO Store upper and lower bound of donation id and donation date, to see the period of time whwre malformed data existed
		if (!isset($this->currentItemTypeIndex[$itemType])) {
			$this->itemBuffer[$itemType] = [];
			$this->currentItemTypeIndex[$itemType] = 0;
			$this->itemCounts[$itemType] = 0;
		}
		$this->itemBuffer[$itemType][$this->currentItemTypeIndex[$itemType]] = $row;
		$this->itemCounts[$itemType]++;
		$this->currentItemTypeIndex[$itemType]++;
		if ( $this->currentItemTypeIndex[$itemType] > $this->bufferSize) {
			$this->currentItemTypeIndex[$itemType] = 0;
		}
	}

	public function getItemCounts(): array {
		return $this->itemCounts;
	}

	public function getItemSample(): array {
		return $this->itemBuffer;
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

	private PaymentReferenceCode $anonymisedPaymentReferenceCode;

	private ResultObject $errors;
	private ResultObject $warnings;

	public function __construct(
		private Connection $db
	) {
		$this->idGenerator = new NullGenerator();
		$this->anonymisedPaymentReferenceCode = new PaymentReferenceCode('AA','AAAAAA','A');
		$this->errors = new ResultObject( 5 );
		$this->warnings = new ResultObject( 5 );
	}

	public function convertDonations() {

		$this->db->getNativeConnection()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
		$qb = $this->db->createQueryBuilder();
		$qb->select( 'd.id', 'betrag AS amount', 'periode AS intervalInMonths', 'zahlweise AS paymentType',
			'ueb_code AS transferCode', 'data', 'status', 'dt_new AS donationDate', 'ps.confirmed_at AS valuationDate',
			'p.id AS paymentId' )
			->from( 'spenden', 'd' )
			->leftJoin( 'd', 'donation_payment', 'p', 'd.payment_id = p.id' )
			->leftJoin( 'p', 'donation_payment_sofort', 'ps', 'ps.id = p.id' )
			->setMaxResults(100000)
		;

		$result = $qb->executeQuery();
		$processedPayments = 0;
		foreach ( $result->iterateAssociative() as $row ) {
			if ( $row['data'] ) {
				$row['data'] = unserialize( base64_decode( $row['data'] ) );
			}

			// Skip payments
			if ($row['paymentType'] === 'PPL' && $row['status'] === 'D') {
				$this->warnings->add('Skipped deleted paypal payment', ['id' => $row['id']]);
				continue;
			}


			try {
				$payment = $this->newPayment( $row );
				// We might actually save the payment here later
			} catch ( \Throwable $e ) {
				$msg = $e->getMessage();
				$this->errors->add($msg, $row);
			}
			$processedPayments++;
		}
		return [ $processedPayments, $this->errors, $this->warnings ];
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
			$this->getAmount($row),
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
			$this->getAmount($row),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) )
		);
		if ( $row['status'] === Donation::STATUS_EXTERNAL_INCOMPLETE ) {
			return $payment;
		}
		if (empty($row['data']['ext_payment_timestamp'])) {
			$this->warnings->add('Payment without timestamp', $row);
			$row['data']['ext_payment_timestamp'] = $row['donationDate'];
		}
		// TODO convert followup payments, probably using reflection
		$payment->bookPayment( $this->getBookingData( self::PPL_LEGACY_KEY_MAP, $row['data'] ), $this->idGenerator );
		return $payment;
	}

	private function newSofortPayment( array $row ): SofortPayment {
		$paymentReferenceCode = empty( $row['transferCode'] ) ? null : PaymentReferenceCode::newFromString( $row['transferCode'] );
		$interval = PaymentInterval::from( intval( $row['intervalInMonths'] ) );
		if ($interval !== PaymentInterval::OneTime ) {
			$this->warnings->add('Recurring interval for sofort payment', [ 'id' => $row['id'], 'interval' => $interval->value ] );
			$interval = PaymentInterval::OneTime;
		}
		$payment = SofortPayment::create(
			intval( $row['paymentId'] ),
			$this->getAmount($row),
			$interval,
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
			$this->getAmount($row),
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
		$paymentReferenceCode = $this->getPaymentReferenceCode($row);
		$payment = BankTransferPayment::create(
			intval( $row['paymentId'] ),
			$this->getAmount($row),
			PaymentInterval::from( intval( $row['intervalInMonths'] ) ),
			$paymentReferenceCode
		);
		if ($payment->getPaymentReferenceCode() === $this->anonymisedPaymentReferenceCode->getFormattedCode()) {
			$payment->anonymise();
		}
		// TODO uncomment when https://github.com/wmde/fundraising-payments/pull/95 is merged
		//if ( $row['status'] == Donation::STATUS_CANCELLED ) {
		//	$payment->cancel();
		//}
		return $payment;
	}

	private function getPaymentReferenceCode(array $row): ?PaymentReferenceCode
	{
		if ( empty($row['transferCode'])) {
			return $this->anonymisedPaymentReferenceCode;
		}
		if (!preg_match('/^\w{2}-\w{3}-\w{3}-\w$/', $row['transferCode'])) {
			$this->warnings->add('Legacy transfer code pattern, omitting', ['id' => $row['id'], 'transferCode' => $row['transferCode']] );
			return $this->anonymisedPaymentReferenceCode;
		}
		return PaymentReferenceCode::newFromString($row['transferCode']);
	}

	private function getAmount(array $row): Euro
	{
		$amount = $row['amount'];
		if (empty($amount)) {
			$this->warnings->add('Converted empty amount to 0', ['id' => $row['id'], 'amount' => $row['amount']]);
			$amount = '0';
		}
		return Euro::newFromString($amount);
	}

}

$db = DriverManager::getConnection( $config );
$converter = new DonationToPaymentConverter( $db );
/** @var array{0:int,1:ResultObject,2:ResultObject} */
[ $processedPayments, $errors, $warnings ] = $converter->convertDonations();

$errorStats = $errors->getItemCounts();
$errorCount = array_sum($errorStats);
printf( "Processed %d donations, with %d errors (%.2f%%)\n", $processedPayments, $errorCount, ( $errorCount * 100 ) / $processedPayments );
print_r($errorStats);
//print_r($errors->getItemSample());
