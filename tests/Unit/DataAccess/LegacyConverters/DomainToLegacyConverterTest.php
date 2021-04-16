<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter
 */
class DomainToLegacyConverterTest extends TestCase {

	/**
	 * @dataProvider getPaymentMethodsAndTransferCodes
	 */
	public function testGivenPaymentMethodWithBankTransferCode_converterGetsCodeFromPayment( string $expectedOutput, Donation $donation ): void {
		$converter = new DomainToLegacyConverter();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

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

		$conversionResult = $converter->convert( $donation, $doctrineDonation );
		$data = $conversionResult->getDecodedData();

		$this->assertArrayHasKey( 'untouched', $data, 'Unrelated (legacy) data should be preserved' );
		$this->assertArrayHasKey( 'another', $data, 'Unrelated (legacy) data should be preserved' );
		$this->assertArrayHasKey( 'vorname', $data );
		$this->assertSame( 'value', $data['untouched'] );
		$this->assertSame( 'untouched', $data['another'] );
		$this->assertNotSame( 'potato', $data['vorname'], 'Person-related data should change' );
	}

	public function testTransactionIdsOfChildDondationsAreConverted(): void {
		$converter = new DomainToLegacyConverter();
		$transactionId = '16R12136PU8783961';
		$fakeChildId = 2;
		$donation = ValidDonation::newBookedPayPalDonation();
		$donation->getPaymentMethod()->getPayPalData()->addChildPayment( $transactionId, $fakeChildId );
		$doctrineDonation = new DoctrineDonation();

		$conversionResult = $converter->convert( $donation, $doctrineDonation );
		$data = $conversionResult->getDecodedData();

		$this->assertSame( [ '16R12136PU8783961' => 2 ], $data['transactionIds'] );
	}

	public function testCreditCardWithExpiryDateIsConverted(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newBookedCreditCardDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );
		$data = $doctrineDonation->getDecodedData();

		$this->assertSame( '9/2001', $data['mcp_cc_expiry_date'] );
	}

	public function testCreditCardWithOutExpiryDateIsConverted(): void {
		$converter = new DomainToLegacyConverter();
		$donation = ValidDonation::newIncompleteCreditCardDonation();

		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );
		$data = $doctrineDonation->getDecodedData();

		$this->assertSame( '', $data['mcp_cc_expiry_date'] );
	}
}
