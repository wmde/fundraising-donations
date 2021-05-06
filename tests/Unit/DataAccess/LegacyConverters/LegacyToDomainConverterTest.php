<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Tests\Data\IncompleteDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentWithoutAssociatedData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter
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

	public function testGivenSofortDonation_converterFillsSofrtPaymentData(): void {
		$doctrineDonation = ValidDoctrineDonation::newSofortDonation();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		/** @var SofortPayment $paymentMethod */
		$paymentMethod = $donation->getPaymentMethod();

		$this->assertNotNull( $paymentMethod->getConfirmedAt() );
		$this->assertSame( ValidDonation::PAYMENT_BANK_TRANSFER_CODE, $paymentMethod->getBankTransferCode() );
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

	public function testGivenDonationWithUnknownPayment_converterCreatesPaymentWithoutAssociatedData(): void {
		$doctrineDonation = ValidDoctrineDonation::newDonationWithCash();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$paymentMethod = $donation->getPaymentMethod();

		$this->assertInstanceOf( PaymentWithoutAssociatedData::class, $paymentMethod );
		$this->assertSame( 'CSH', $paymentMethod->getId() );
	}

	public function testGivenDonationWithCancelledStatus_converterMarksDonationAsCancelled(): void {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$this->assertTrue( $donation->isCancelled() );
	}

	/**
	 * @dataProvider cancelledDonations
	 */
	public function testGivenDonationWithCancelledStatus_DonationStatusMatchesPaymentType( DoctrineDonation $dd, string $expectedStatus ): void {
		$converter = new LegacyToDomainConverter();
		$donation = $converter->createFromLegacyObject( $dd );

		$this->assertSame( $expectedStatus, $donation->getStatus() );
	}

	public function cancelledDonations(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_PROMISE ];

		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_NEW ];

		// this case should never occur, but tested anyway
		$doctrineDonation = ValidDoctrineDonation::newIncompletePaypalDonation();
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE ];
	}

	public function testGivenDonationWithModerationNeededStatus_converterMarksDonationAsToBeModerated(): void {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	/**
	 * @dataProvider donationsMarkedForModeration
	 */
	public function testGivenDonationWithModerationNeededStatus_DonationStatusMatchesPaymentType( DoctrineDonation $dd, string $expectedStatus ): void {
		$converter = new LegacyToDomainConverter();
		$donation = $converter->createFromLegacyObject( $dd );

		$this->assertSame( $expectedStatus, $donation->getStatus() );
	}

	public function donationsMarkedForModeration(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_PROMISE ];

		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_NEW ];

		// this case should never occur, but tested anyway
		$doctrineDonation = ValidDoctrineDonation::newIncompletePaypalDonation();
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE ];
	}

	/**
	 * @dataProvider donationsWithExternalPaymentAndModifiedState
	 */
	public function testGivenCanceledOrModeratedDonationWithExternalPayment_statusReflectsPaymentState( DoctrineDonation $dd, string $expectedStatus, string $description ): void {
		$converter = new LegacyToDomainConverter();
		$donation = $converter->createFromLegacyObject( $dd );

		$this->assertSame( $expectedStatus, $donation->getStatus(), "Failed expectation for: $description" );
	}

	public function donationsWithExternalPaymentAndModifiedState(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newIncompletePaypalDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Moderated incomplete paypal donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Canceled incomplete paypal donation (broken legacy data)' ];

		$doctrineDonation = ValidDoctrineDonation::newPaypalDoctrineDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Moderated booked paypal donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Canceled booked paypal donation (broken legacy data)' ];

		$doctrineDonation = ValidDoctrineDonation::newIncompleteCreditCardDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Moderated incomplete credit card donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Canceled incomplete credit card donation (broken legacy data)' ];

		$doctrineDonation = ValidDoctrineDonation::newCreditCardDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Moderated booked credit card donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Canceled booked credit card donation (broken legacy data)' ];
	}

	/**
	 * Remove this test when we remove status from Donation, see https://phabricator.wikimedia.org/T281853
	 */
	public function testGivenUnknownPaymentMethod_stateIsPromised(): void {
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( ValidDoctrineDonation::newDonationWithCash() );

		$this->assertSame( Donation::STATUS_PROMISE, $donation->getStatus() );
	}
}
