<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Tests\Data\IncompleteDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;

/**
 * @covers WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
 */
class LegacyToDomainConverterTest extends TestCase {
	public function testGivenIncompletePaypalData_converterFillsPaypalDataWithDefaults(): void {
		$doctrineDonation = IncompleteDoctrineDonation::newPaypalDonationWithMissingFields();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		/** @var PayPalPayment $paypalPayment */
		$paypalPayment = $donation->getPaymentMethod();

		$this->assertNotNull( $paypalPayment->getPayPalData() );
		$this->assertSame( '', $paypalPayment->getPayPalData()->getFirstName() );
	}

	public function testGivenIncompleteTrackingData_converterFillsTrackingDataWithDefaults(): void {
		$doctrineDonation = IncompleteDoctrineDonation::newPaypalDonationWithMissingTrackingData();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$info = $donation->getTrackingInfo();

		$this->assertNotNull( $info );
		$this->assertSame( 0, $info->getTotalImpressionCount() );
		$this->assertSame( 0, $info->getSingleBannerImpressionCount() );
		$this->assertSame( '', $info->getTracking() );
	}

	public function testGivenIncompleteBankData_converterFillsBankDataWithDefaults(): void {
		$doctrineDonation = IncompleteDoctrineDonation::newDirectDebitDonationWithMissingFields();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		/** @var DirectDebitPayment $paymentMethod */
		$paymentMethod = $donation->getPaymentMethod();

		$this->assertNotNull( $paymentMethod->getBankData() );
		$this->assertSame( '', $paymentMethod->getBankData()->getIban()->toString() );
	}

	public function testGivenCompleteBankData_converterAddsTransferCode(): void {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		/** @var BankTransferPayment $paymentMethod */
		$paymentMethod = $donation->getPaymentMethod();

		$this->assertSame( ValidDonation::PAYMENT_BANK_TRANSFER_CODE, $paymentMethod->getBankTransferCode() );
	}

	public function testGivenIncompleteCreditcardData_converterFillsCreditcardDataWithDefaults(): void {
		$doctrineDonation = IncompleteDoctrineDonation::newCreditcardDonationWithMissingFields();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		/** @var CreditCardPayment $paymentMethod */
		$paymentMethod = $donation->getPaymentMethod();

		$this->assertNotNull( $paymentMethod->getCreditCardData() );
		$this->assertSame( '', $paymentMethod->getCreditCardData()->getTitle() );
	}

	public function testGivenDataSetWithExportDate_donationIsMarkedAsExported(): void {
		$doctrineDonation = ValidDoctrineDonation::newExportedirectDebitDoctrineDonation();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );

		$this->assertTrue( $donation->isExported(), 'Donation should be marked as exported' );
	}

	public function testGivenPaypalDonationWithMultipleTransactionIds_converterCreatesChildPaymentEntries(): void {
		$transactionIds = [
			'16R12136PU8783961' => 2,
			'1A412136PU8783961' => 3
		];
		$doctrineDonation = ValidDoctrineDonation::newPaypalDoctrineDonation();
		$doctrineDonation->encodeAndSetData( array_merge(
			$doctrineDonation->getDecodedData(),
			[ 'transactionIds' => $transactionIds ]
		) );
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		/** @var PayPalPayment $paypalPayment */
		$paypalPayment = $donation->getPaymentMethod();

		$this->assertEquals( $transactionIds, $paypalPayment->getPaypalData()->getAllChildPayments() );
	}

	public function testGivenPaypalDonationWithNumericalTransactionIds_converterCreatesChildPaymentEntries(): void {
		// Old versions of the PayPal API used numerical instead of alphanumeric transaction IDs
		// This is a test to see if these old donations can be converted
		$transactionIds = [
			'123456789' => 2,
		];
		$doctrineDonation = ValidDoctrineDonation::newPaypalDoctrineDonation();
		$doctrineDonation->encodeAndSetData( array_merge(
			$doctrineDonation->getDecodedData(),
			[ 'transactionIds' => $transactionIds ]
		) );
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		/** @var PayPalPayment $paypalPayment */
		$paypalPayment = $donation->getPaymentMethod();

		$this->assertEquals( $transactionIds, $paypalPayment->getPaypalData()->getAllChildPayments() );
	}

}
