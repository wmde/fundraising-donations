<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter
 */
class DomainToLegacyConverterTest extends TestCase {

	/**
	 * @dataProvider getPaymentMethodsAndTransferCodes
	 */
	public function testGivenPaymentMethodWithBankTransferCode_converterGetsCodeFromPayment( string $expectedOutput, Donation $donation ): void {
		$this->markTestIncomplete( 'Converter needs "get payment" use case' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertEquals( $expectedOutput, $doctrineDonation->getBankTransferCode() );
	}

	public function getPaymentMethodsAndTransferCodes(): array {
		return [
			[ ValidDonation::PAYMENT_BANK_TRANSFER_CODE, ValidDonation::newBankTransferDonation() ],
			[ ValidDonation::PAYMENT_BANK_TRANSFER_CODE, ValidDonation::newSofortDonation() ],
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

		$conversionResult = $converter->convert( $donation, $doctrineDonation, [] );
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

		$conversionResult = $converter->convert( $donation, $doctrineDonation, [] );
		$data = $conversionResult->getDecodedData();

		$this->assertSame( [ '16R12136PU8783961' => 2 ], $data['transactionIds'] );
	}

	public function testGivenCancelledDonation_convertsToCancelledStatusDoctrineDonation(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newCancelledBankTransferDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertSame( DoctrineDonation::STATUS_CANCELLED, $doctrineDonation->getStatus() );
	}

	private function makeGenericModerationReason(): ModerationReason {
		return new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
	}

	public function testGivenDonationMarkedForModeration_convertsToModerationStatusDoctrineDonation(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertSame( DoctrineDonation::STATUS_MODERATION, $doctrineDonation->getStatus() );
	}

	public function testGivenDonationWithoutModerationOrCancellation_paymentStatusIsPreserved(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertSame( DoctrineDonation::STATUS_PROMISE, $doctrineDonation->getStatus() );
	}

	public function testGivenCancelledDonationThatIsMarkedForModeration_convertsToCancelledStatusDoctrineDonation(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$donation->cancelWithoutChecks();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertSame( DoctrineDonation::STATUS_CANCELLED, $doctrineDonation->getStatus() );
	}

	public function testGivenModeratedDonation_convertsToDoctrineDonationHavingModerationReasons(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();
		$moderationReasons = [ $this->makeGenericModerationReason(), new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH ) ];
		$donation->markForModeration( ...$moderationReasons );

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertEquals( $moderationReasons, $doctrineDonation->getModerationReasons()->toArray() );
	}

	public function testGivenDirectDebitDonation_statusIsSet(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newDirectDebitDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertSame( DoctrineDonation::STATUS_NEW, $doctrineDonation->getStatus() );
	}

	public function testGivenBankTransferDonation_statusIsSet(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertSame( DoctrineDonation::STATUS_PROMISE, $doctrineDonation->getStatus() );
	}

	/**
	 * @dataProvider incompleteDonationProvider
	 * @param Donation $donation
	 */
	public function testGivenIncompleteDonation_statusIsSet( Donation $donation ): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

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

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [] );

		$this->assertSame( $expectedStatus, $doctrineDonation->getStatus() );
	}

	public function externallyBookedDonationProvider(): iterable {
		yield [ ValidDonation::newBookedAnonymousPayPalDonation(), DoctrineDonation::STATUS_EXTERNAL_BOOKED ];
		yield [ ValidDonation::newBookedCreditCardDonation(), DoctrineDonation::STATUS_EXTERNAL_BOOKED ];
		yield [ ValidDonation::newCompletedSofortDonation(), DoctrineDonation::STATUS_PROMISE ];
	}

	public function testDonationWithGivenUnknownPaymentType_settingStatusFails(): void {
		$this->markTestIncomplete( 'status derived from payment needs to be reworked' );
		$donation = new Donation( null,
			DoctrineDonation::STATUS_NEW,
			ValidDonation::newEmailOnlyDonor(),
			new InvalidPayment(),
			false,
			DonationTrackingInfo::newBlankTrackingInfo()
		);
		$converter = new DomainToLegacyConverter();

		$this->expectException( \DomainException::class );

		$converter->convert( $donation, new DoctrineDonation(), [] );
	}

	public function testGivenExistingModerationReasons_theyOverrideIdenticalDonationModerationReasons(): void {
		$reason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$reason2 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' );
		$reason3 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'street' );
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( $reason1, $reason2, $reason3 );
		$existingReason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$existingReason2 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [ $existingReason1, $existingReason2 ] );
		$doctrineModerationReasons = $doctrineDonation->getModerationReasons();

		$this->assertSame( [ $existingReason1, $existingReason2, $reason3 ], $doctrineModerationReasons->toArray() );
	}

	public function testGivenExistingModerationReasonsWhichDoNotExistInDonation_theyAreNotStored(): void {
		$reason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( $reason1 );
		$existingReason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$existingReason2 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' );
		$existingReason3 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'street' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), [ $existingReason1, $existingReason2, $existingReason3 ] );
		$doctrineModerationReasons = $doctrineDonation->getModerationReasons();

		$this->assertSame( [ $existingReason1 ], $doctrineModerationReasons->toArray() );
	}

}
