<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Tests\Data\InvalidPayment;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\InitialDonationStatusPicker;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\InitialDonationStatusPicker
 */
class InitialDonationStatusPickerTest extends TestCase {
	public function testGetInitialDonationStatus(): void {
		$picker = new InitialDonationStatusPicker();

		$this->assertSame( 'N', $picker( ValidDonation::newDirectDebitPayment() ) );
		$this->assertSame( 'Z', $picker( ValidDonation::newBankTransferPayment() ) );

		$this->assertSame( 'X', $picker( ValidDonation::newPayPalPayment() ) );
		$this->assertSame( 'X', $picker( ValidDonation::newCreditCardPayment() ) );
		$this->assertSame( 'X', $picker( new InvalidPayment() ) );
	}
}
