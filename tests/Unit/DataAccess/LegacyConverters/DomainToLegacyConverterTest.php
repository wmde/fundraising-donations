<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Tests\Data\InvalidPayment;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter
 */
class DomainToLegacyConverterTest extends TestCase {

	/**
	 * @dataProvider getPaymentMethodsAndTransferCodes
	 */
	public function testGivenPaymentMethodWithBankTransferCode_converterGetsCodeFromPayment( string $expectedOutput, Donation $donation ): void {
		$converter = new DomainToLegacyConverter();
		$legacyData = new LegacyPaymentData(
			99,
			9,
			'*',
			$expectedOutput? ['ueb_code' => $expectedOutput]: [],
			'X'
		);

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyData );

		$this->assertEquals( $expectedOutput, $doctrineDonation->getBankTransferCode() );
	}

	public function getPaymentMethodsAndTransferCodes(): array {
		return [
			[ ValidPayments::PAYMENT_BANK_TRANSFER_CODE, ValidDonation::newBankTransferDonation(),  ],
			[ ValidPayments::PAYMENT_BANK_TRANSFER_CODE, ValidDonation::newSofortDonation() ],
			[ '', ValidDonation::newBookedCreditCardDonation() ],
		];
	}

	public function testExistingDataInDataBlobIsRetainedOrUpdated(): void {
		$converter = new DomainToLegacyConverter();
		// This donation is from a person, so person-related data should be overwritten
		$donation = ValidDonation::newBankTransferDonation();
		$doctrineDonation = new DoctrineDonation();
		$doctrineDonation->encodeAndSetData( array_merge(
			$doctrineDonation->getDecodedData(),
			[
				'untouched' => 'value',
				'vorname' => 'potato',
				'another' => 'untouched',
			]
		) );

		$conversionResult = $converter->convert( $donation, $doctrineDonation );
		$data = $conversionResult->getDecodedData();

		$this->assertArrayHasKey( 'untouched', $data, 'Unrelated (legacy) data should be preserved' );
		$this->assertArrayHasKey( 'another', $data, 'Unrelated (legacy) data should be preserved' );
		$this->assertArrayHasKey( 'vorname', $data );
		$this->assertSame( 'value', $data['untouched'] );
		$this->assertSame( 'untouched', $data['another'] );
		$this->assertNotSame( 'potato', $data['vorname'], 'Person-related data should change' );
	}

	public function testTransactionIdsOfChildDonationsAreConverted(): void {
		$this->markTestIncomplete( 'Converter needs "get payment" use case' );
		$converter = new DomainToLegacyConverter();
		$transactionId = '16R12136PU8783961';
		$fakeChildId = 2;
		$donation = ValidDonation::newBookedPayPalDonation();
		// TODO Prepare real followup payments in the database instead
		// $donation->getPaymentMethod()->getPayPalData()->addChildPayment( $transactionId, $fakeChildId );
		$doctrineDonation = new DoctrineDonation();

		$conversionResult = $converter->convert( $donation, $doctrineDonation );
		$data = $conversionResult->getDecodedData();

		$this->assertSame( [ '16R12136PU8783961' => 2 ], $data['transactionIds'] );
	}

	public function testGivenCancelledDonation_convertsToCancelledStatusDoctrineDonation(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newCancelledBankTransferDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( DoctrineDonation::STATUS_CANCELLED, $doctrineDonation->getStatus() );
	}

	public function testGivenDonationMarkedForModeration_convertsToModerationStatusDoctrineDonation(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( DoctrineDonation::STATUS_MODERATION, $doctrineDonation->getStatus() );
	}

	public function testGivenDonationWithoutModerationOrCancellation_paymentStatusIsPreserved(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( DoctrineDonation::STATUS_PROMISE, $doctrineDonation->getStatus() );
	}

	public function testGivenCancelledDonationThatIsMarkedForModeration_convertsToCancelledStatusDoctrineDonation(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$donation->cancelWithoutChecks();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( DoctrineDonation::STATUS_CANCELLED, $doctrineDonation->getStatus() );
	}


	//TODO can probably be deleted
	public function testGivenDirectDebitDonation_statusIsSet(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newDirectDebitDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( DoctrineDonation::STATUS_NEW, $doctrineDonation->getStatus() );
	}

	//TODO can probably be deleted
	public function testGivenBankTransferDonation_statusIsSet(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( DoctrineDonation::STATUS_PROMISE, $doctrineDonation->getStatus() );
	}

	/**
	 * @dataProvider incompleteDonationProvider
	 * @param Donation $donation
	 */
	public function testGivenIncompleteDonation_statusIsSet( Donation $donation ): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE, $doctrineDonation->getStatus() );
	}

	public function incompleteDonationProvider(): iterable {
		// The credit card data tests both null and empty credit card transaction data
		yield [ ValidDonation::newIncompleteCreditCardDonation() ];
		yield [ ValidDonation::newIncompleteAnonymousCreditCardDonation() ];
		yield [ ValidDonation::newIncompleteAnonymousPayPalDonation() ];
		yield [ ValidDonation::newIncompleteSofortDonation() ];
	}

	/**
	 * @dataProvider externallyBookedDonationProvider
	 * @param Donation $donation
	 * @param string $expectedStatus
	 */
	public function testGivenBookedDonation_statusIsSet( Donation $donation, string $expectedStatus ): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		$this->assertSame( $expectedStatus, $doctrineDonation->getStatus() );
	}

	public function externallyBookedDonationProvider(): iterable {
		yield [ ValidDonation::newBookedAnonymousPayPalDonation(), DoctrineDonation::STATUS_EXTERNAL_BOOKED ];
		yield [ ValidDonation::newBookedCreditCardDonation(), DoctrineDonation::STATUS_EXTERNAL_BOOKED ];
		yield [ ValidDonation::newCompletedSofortDonation(), DoctrineDonation::STATUS_PROMISE ];
	}
}
