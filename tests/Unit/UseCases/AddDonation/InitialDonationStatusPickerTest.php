<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\InitialDonationStatusPicker;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\InitialDonationStatusPicker
 */
class InitialDonationStatusPickerTest extends TestCase {
	public function testGetInitialDonationStatus(): void {
		$picker = new InitialDonationStatusPicker();

		$this->assertSame( 'N', $picker( 'BEZ' ) );
		$this->assertSame( 'Z', $picker( 'UEB' ) );

		$this->assertSame( 'X', $picker( 'SUB' ) );
		$this->assertSame( 'X', $picker( 'MCP' ) );
		$this->assertSame( 'X', $picker( 'foo' ) );
	}
}
