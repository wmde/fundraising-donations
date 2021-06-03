<?php
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedTransferCodeGenerator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\PaymentFactory;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\PaymentFactory
 */
class PaymentFactoryTest extends TestCase {
	private const AMOUNT = '49.99';
	public const PAYMENT_BIC = 'INGDDEFFXXX';
	public const PAYMENT_IBAN = 'DE12500105170648489890';

	public function testDirectDebitRequestCreatesDirectDebitPayment(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator( 'BANK' ) );
		$bankData = new BankData();
		$bankData->setIban( new Iban( self::PAYMENT_IBAN ) );
		$bankData->setBic( self::PAYMENT_BIC );
		$request = $this->newMinimumDonationRequest( PaymentMethod::DIRECT_DEBIT );
		$request->setBankData( $bankData );

		$payment = $factory->getPaymentFromRequest( $request );
		/** @var DirectDebitPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();

		$this->assertEquals( Euro::newFromCents( 4999 ), $payment->getAmount() );
		$this->assertSame( 0, $payment->getIntervalInMonths() );
		$this->assertInstanceOf( DirectDebitPayment::class, $paymentMethod );
		$this->assertEquals( $bankData, $paymentMethod->getBankData() );
	}

	public function testBankTransferRequestCreatesBankTransferPayment(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator( 'BANK' ) );

		$payment = $factory->getPaymentFromRequest( $this->newMinimumDonationRequest( PaymentMethod::BANK_TRANSFER ) );
		/** @var BankTransferPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();

		$this->assertEquals( Euro::newFromCents( 4999 ), $payment->getAmount() );
		$this->assertSame( 0, $payment->getIntervalInMonths() );
		$this->assertInstanceOf( BankTransferPayment::class, $paymentMethod );
		$this->assertSame( 'XRBANK', $paymentMethod->getBankTransferCode() );
	}

	public function testBankTransferRequestWithNamedDonorCreatesBankTransferPaymentWithDifferentTransferCodePrefix(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator( 'BANK' ) );
		$request = $this->newMinimumDonationRequest( PaymentMethod::BANK_TRANSFER );
		$request->setDonorType( DonorType::COMPANY() );

		$payment = $factory->getPaymentFromRequest( $request );
		/** @var BankTransferPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();

		$this->assertInstanceOf( BankTransferPayment::class, $paymentMethod );
		$this->assertSame( 'XWBANK', $paymentMethod->getBankTransferCode() );
	}

	public function testCreditCardRequestCreatesCreditCardPayment(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator() );

		$payment = $factory->getPaymentFromRequest( $this->newMinimumDonationRequest( PaymentMethod::CREDIT_CARD ) );
		/** @var CreditCardPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();

		$this->assertEquals( Euro::newFromCents( 4999 ), $payment->getAmount() );
		$this->assertSame( 0, $payment->getIntervalInMonths() );
		$this->assertInstanceOf( CreditCardPayment::class, $paymentMethod );
		$this->assertFalse( $paymentMethod->paymentCompleted(), 'New credit card payments should not be booked' );
	}

	public function testPayPalRequestCreatesPayPalPayment(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator() );

		$payment = $factory->getPaymentFromRequest( $this->newMinimumDonationRequest( PaymentMethod::PAYPAL ) );
		/** @var PayPalPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();

		$this->assertEquals( Euro::newFromCents( 4999 ), $payment->getAmount() );
		$this->assertSame( 0, $payment->getIntervalInMonths() );
		$this->assertInstanceOf( PayPalPayment::class, $paymentMethod );
		$this->assertFalse( $paymentMethod->paymentCompleted(), 'New PayPal payments should not be booked' );
	}

	public function testSofortRequestCreatesSofortPayment(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator( 'BANK' ) );

		$payment = $factory->getPaymentFromRequest( $this->newMinimumDonationRequest( PaymentMethod::SOFORT ) );
		/** @var SofortPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();

		$this->assertEquals( Euro::newFromCents( 4999 ), $payment->getAmount() );
		$this->assertSame( 0, $payment->getIntervalInMonths() );
		$this->assertInstanceOf( SofortPayment::class, $paymentMethod );
		$this->assertSame( 'XRBANK', $paymentMethod->getBankTransferCode() );
		$this->assertFalse( $paymentMethod->paymentCompleted(), 'New Sofort payments should not be booked' );
	}

	public function testSofortRequestWithNamedDonorCreatesSofortPaymentWithDifferentTransferCodePrefix(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator( 'BANK' ) );
		$request = $this->newMinimumDonationRequest( PaymentMethod::SOFORT );
		$request->setDonorType( DonorType::COMPANY() );

		$payment = $factory->getPaymentFromRequest( $request );
		/** @var SofortPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();

		$this->assertInstanceOf( SofortPayment::class, $paymentMethod );
		$this->assertSame( 'XWBANK', $paymentMethod->getBankTransferCode() );
	}

	public function testSettingDifferentAmountAndInterval(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator() );
		$amount = Euro::newFromCents( 100 );
		$interval = 3;
		$donationRequest = new AddDonationRequest();
		$donationRequest->setAmount( $amount );
		$donationRequest->setInterval( $interval );
		$donationRequest->setPaymentType( PaymentMethod::PAYPAL );
		$donationRequest->setDonorType( DonorType::ANONYMOUS() );

		$payment = $factory->getPaymentFromRequest( $donationRequest );

		$this->assertEquals( $amount, $payment->getAmount() );
		$this->assertSame( $interval, $payment->getIntervalInMonths() );
	}

	public function testGivenInvalidPaymentType_throwsADomainException(): void {
		$factory = new PaymentFactory( new FixedTransferCodeGenerator() );
		$request = $this->newMinimumDonationRequest( "CASH" );

		$this->expectException( \UnexpectedValueException::class );

		$factory->getPaymentFromRequest( $request );
	}

	private function newMinimumDonationRequest( string $paymentMethod ): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setAmount( Euro::newFromString( self::AMOUNT ) );
		$donationRequest->setPaymentType( $paymentMethod );
		$donationRequest->setDonorType( DonorType::ANONYMOUS() );
		return $donationRequest;
	}
}
