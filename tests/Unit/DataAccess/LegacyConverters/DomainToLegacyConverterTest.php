<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter
 */
class DomainToLegacyConverterTest extends TestCase {

	/**
	 * @dataProvider getPaymentMethodsAndTransferCodes
	 */
	public function testGetBankTransferCode_identifierIsReturned( string $expectedOutput, PaymentMethod $payment ): void {
		$this->assertEquals( $expectedOutput, DomainToLegacyConverter::getBankTransferCode( $payment ) );
	}

	public function getPaymentMethodsAndTransferCodes(): array {
		return [
			[ 'ffg', new SofortPayment( 'ffg' ) ],
			[ 'hhi', new BankTransferPayment( 'hhi' ) ],
			[ '', new CreditCardPayment() ],
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
}
