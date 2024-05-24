<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

#[CoversClass( DomainToLegacyConverter::class )]
class DomainToLegacyConverterTest extends TestCase {

	#[DataProvider( 'getPaymentMethodsAndTransferCodes' )]
	public function testGivenPaymentMethodWithBankTransferCode_converterGetsCodeFromPayment( string $expectedOutput, Donation $donation ): void {
		$converter = new DomainToLegacyConverter();

		$legacyPaymentData = new LegacyPaymentData(
			99,
			9,
			'UEB',
			$expectedOutput ? [ 'ueb_code' => $expectedOutput ] : [],
		);

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyPaymentData, [] );

		$this->assertEquals( $expectedOutput, $doctrineDonation->getBankTransferCode() );
	}

	/**
	 * @return array<array{string|Donation}>
	 */
	public static function getPaymentMethodsAndTransferCodes(): array {
		return [
			[ ValidPayments::PAYMENT_BANK_TRANSFER_CODE, ValidDonation::newBankTransferDonation(), ],
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

		$legacyPaymentData = new LegacyPaymentData(
			1,
			1,
			'BEZ',
			[],
		);

		$conversionResult = $converter->convert( $donation, $doctrineDonation, $legacyPaymentData, [] );
		$data = $conversionResult->getDecodedData();

		$this->assertArrayHasKey( 'untouched', $data, 'Unrelated (legacy) data should be preserved' );
		$this->assertArrayHasKey( 'another', $data, 'Unrelated (legacy) data should be preserved' );
		$this->assertArrayHasKey( 'vorname', $data );
		$this->assertSame( 'value', $data['untouched'] );
		$this->assertSame( 'untouched', $data['another'] );
		$this->assertNotSame( 'potato', $data['vorname'], 'Person-related data should change' );
	}

	public function testGivenCancelledDonation_convertsToCancelledStatusDoctrineDonation(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$legacyPaymentData = new LegacyPaymentData(
			9999,
			1,
			'UEB',
			[],
		);

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyPaymentData, [] );

		$this->assertSame( DoctrineDonation::STATUS_CANCELLED, $doctrineDonation->getStatus() );
	}

	private function makeGenericModerationReason(): ModerationReason {
		return new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
	}

	public function testGivenDonationMarkedForModeration_convertsToModerationStatusDoctrineDonation(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$legacyPaymentData = new LegacyPaymentData(
			9999,
			1,
			'BEZ',
			[],
		);

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyPaymentData, [] );

		$this->assertSame( DoctrineDonation::STATUS_MODERATION, $doctrineDonation->getStatus() );
	}

	public function testGivenDonationWithoutModerationOrCancellation_paymentStatusIsPreserved(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBankTransferDonation();
		$legacyPaymentData = new LegacyPaymentData(
			9999,
			1,
			'UEB',
			[],
		);

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyPaymentData, [] );

		$this->assertSame( DoctrineDonation::STATUS_PROMISE, $doctrineDonation->getStatus() );
	}

	public function testGivenCancelledDonationThatIsMarkedForModeration_convertsToCancelledStatusDoctrineDonation(): void {
		$converter = new DomainToLegacyConverter();
		$payment = ValidPayments::newBankTransferPayment();
		$payment->cancel();
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$donation->cancelWithoutChecks();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $payment->getLegacyData(), [] );

		$this->assertSame( DoctrineDonation::STATUS_CANCELLED, $doctrineDonation->getStatus() );
	}

	public function testGivenModeratedDonation_convertsToDoctrineDonationHavingModerationReasons(): void {
		$converter = new DomainToLegacyConverter();
		$legacyDataPaymentData = ValidPayments::newBankTransferPayment()->getLegacyData();
		$donation = ValidDonation::newBankTransferDonation();
		$moderationReasons = [ $this->makeGenericModerationReason(), new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH ) ];
		$donation->markForModeration( ...$moderationReasons );

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyDataPaymentData, [] );

		$this->assertEquals( $moderationReasons, $doctrineDonation->getModerationReasons()->toArray() );
	}

	#[DataProvider( 'getStatusValues' )]
	public function testStatusGetsSetAccordingToPaymentType( Donation $donation, Payment $payment, string $expectedStatus ): void {
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $payment->getLegacyData(), [] );

		$this->assertSame( $expectedStatus, $doctrineDonation->getStatus() );
	}

	/**
	 * @return iterable<array{Donation,Payment,string}>
	 */
	public static function getStatusValues(): iterable {
		yield 'direct debit' => [ ValidDonation::newDirectDebitDonation(), ValidPayments::newDirectDebitPayment(), DoctrineDonation::STATUS_NEW ];
		yield 'bank transfer' => [ ValidDonation::newBankTransferDonation(), ValidPayments::newBankTransferPayment(), DoctrineDonation::STATUS_PROMISE ];
		yield 'unbooked paypal' => [ ValidDonation::newIncompleteAnonymousPayPalDonation(), ValidPayments::newPayPalPayment(), DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE ];
		yield 'booked paypal' => [ ValidDonation::newBookedPayPalDonation(), ValidPayments::newBookedPayPalPayment(), DoctrineDonation::STATUS_EXTERNAL_BOOKED ];
		yield 'unbooked credit card' => [ ValidDonation::newIncompleteCreditCardDonation(), ValidPayments::newCreditCardPayment(), DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE ];
		yield 'booked credit card' => [ ValidDonation::newBookedCreditCardDonation(), ValidPayments::newBookedCreditCardPayment(), DoctrineDonation::STATUS_EXTERNAL_BOOKED ];
		yield 'unbooked sofort' => [ ValidDonation::newIncompleteSofortDonation(), ValidPayments::newSofortPayment(), DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE ];
		yield 'booked sofort' => [ ValidDonation::newCompletedSofortDonation(), ValidPayments::newCompletedSofortPayment(), DoctrineDonation::STATUS_PROMISE ];
	}

	public function testLegacyDataGetsSet(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newDirectDebitDonation();
		$legacyPaymentData = new LegacyPaymentData(
			2342,
			1,
			'BEZ',
			[],
		);

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyPaymentData, [] );

		$this->assertSame( 'BEZ', $doctrineDonation->getPaymentType() );
		$this->assertSame( '23.42', $doctrineDonation->getAmount() );
		$this->assertSame( 1, $doctrineDonation->getPaymentIntervalInMonths() );
	}

	public function testGivenExistingModerationReasons_theyOverrideIdenticalDonationModerationReasons(): void {
		$reason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$reason2 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' );
		$reason3 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'street' );
		$legacyDataPaymentData = ValidPayments::newDirectDebitPayment()->getLegacyData();
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( $reason1, $reason2, $reason3 );
		$existingReason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$existingReason2 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyDataPaymentData, [ $existingReason1, $existingReason2 ] );
		$doctrineModerationReasons = $doctrineDonation->getModerationReasons();

		$this->assertSame( [ $existingReason1, $existingReason2, $reason3 ], $doctrineModerationReasons->toArray() );
	}

	public function testGivenExistingModerationReasonsWhichDoNotExistInDonation_theyAreNotStored(): void {
		$reason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$legacyDataPaymentData = ValidPayments::newDirectDebitPayment()->getLegacyData();
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( $reason1 );
		$existingReason1 = new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH );
		$existingReason2 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' );
		$existingReason3 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'street' );
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation(), $legacyDataPaymentData, [ $existingReason1, $existingReason2, $existingReason3 ] );
		$doctrineModerationReasons = $doctrineDonation->getModerationReasons();

		$this->assertSame( [ $existingReason1 ], $doctrineModerationReasons->toArray() );
	}
}
