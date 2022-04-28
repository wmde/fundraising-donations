<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Tests\Data\InvalidPayment;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\InitialDonationStatusPicker;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\InitialDonationStatusPicker
 */
class InitialDonationStatusPickerTest extends TestCase {
	public function testGetInitialDonationStatus(): void {
		$picker = new InitialDonationStatusPicker();

		$this->assertSame( 'N', $picker( ValidPayments::newDirectDebitPayment() ) );
		$this->assertSame( 'Z', $picker( ValidPayments::newBankTransferPayment() ) );

		$this->assertSame( 'X', $picker( ValidPayments::newPayPalPayment() ) );
		$this->assertSame( 'X', $picker( ValidPayments::newCreditCardPayment() ) );
		$this->assertSame( 'X', $picker( new InvalidPayment() ) );
	}
}
