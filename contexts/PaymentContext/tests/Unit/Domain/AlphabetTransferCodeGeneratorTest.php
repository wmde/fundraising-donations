<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Tests\Unit\Domain;

use WMDE\Fundraising\Frontend\PaymentContext\Domain\AlphabetTransferCodeGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers WMDE\Fundraising\Frontend\PaymentContext\Domain\AlphabetTransferCodeGenerator
 */
class AlphabetTransferCodeGeneratorTest extends TestCase {

	public function testChecksumIsAppendedToNumber(): void {
		$generator = AlphabetTransferCodeGenerator::deterministicAlphanumericCodeGenerator();

		$this->assertSame( '1-1', $generator->generateTransferCode( '' ) );
		$this->assertSame( '2-2', $generator->generateTransferCode( '' ) );
		$this->assertSame( '3-3', $generator->generateTransferCode( '' ) );
	}

	public function testPrefixNumberIsPassedToChecksumFunction(): void {
		$generator = AlphabetTransferCodeGenerator::deterministicAlphanumericCodeGenerator();
		$this->assertSame( '11-11', $generator->generateTransferCode( '1' ) );
		$this->assertSame( '12-12', $generator->generateTransferCode( '1' ) );
	}

	public function testPrefixNumberIsConvertedBeforeItIsPassedToChecksumFunction(): void {
		$generator = AlphabetTransferCodeGenerator::deterministicHexCodeGenerator();
		// A hex => 10 dec, 101 dec => 65 hex
		$this->assertSame( 'A1-65', $generator->generateTransferCode( 'A' ) );
		$this->assertSame( 'A2-66', $generator->generateTransferCode( 'A' ) );
	}

	public function testInvalidCharactersInPrefixAreStrippedBeforeItIsPassedToChecksumFunction(): void {
		$generator = AlphabetTransferCodeGenerator::deterministicAlphanumericCodeGenerator();
		$this->assertSame( '1-1-11', $generator->generateTransferCode( '1-' ) );
		$this->assertSame( '9-9-2-992', $generator->generateTransferCode( '9-9-' ) );
	}

	public function testGeneratedCodesConformToPattern(): void {
		$generator = AlphabetTransferCodeGenerator::randomCodeGenerator();

		for ( $i = 0; $i < 42; $i++ ) {
			$this->assertRegExp(
				'/^XR-[ACDEFKLMNPRTWXYZ349]{3}-[ACDEFKLMNPRTWXYZ349]{3}-[ACDEFKLMNPRTWXYZ349]$/',
				$generator->generateTransferCode( 'XR-' )
			);
		}
	}

	/**
	* @dataProvider validCodeProvider
	*/
	public function testKnownValidCodes( string $code ): void {
		$generator = AlphabetTransferCodeGenerator::randomCodeGenerator();

		$this->assertTrue( $generator->validateCode( $code ) );
	}

	public function validCodeProvider(): iterable {
		yield [ 'XR-APT-ERA-F' ];
		yield [ 'X-R-F-DK-PRT-C' ];
		yield [ 'X-W-MKZ-4C3-L' ];
		yield [ 'XW-49XY-NL-E' ];
	}

	/**
	 * @dataProvider invalidCodeProvider
	 */
	public function testInvalidCodes( string $code ): void {
		$generator = AlphabetTransferCodeGenerator::randomCodeGenerator();

		$this->assertFalse( $generator->validateCode( $code ) );
	}

	public function invalidCodeProvider(): iterable {
		yield 'Empty code' => [ '' ];
		yield 'Without checksum' => [ 'XR-APT-ERA' ];
		yield 'Extra character' => [ 'XR-APT-EXRA-F' ];
		yield 'Not allowed character' => [ 'XR-APT-E0A-F' ];
		yield 'Invalid checksum' => [ 'XW-49XY-NL-D' ];
	}

	public function testGeneratedCodesAreValid(): void {
		$generator = AlphabetTransferCodeGenerator::randomCodeGenerator();

		for ( $i = 0; $i < 42; $i++ ) {
			$this->assertTrue(
				$generator->validateCode( $generator->generateTransferCode( 'XR-' ) )
			);
		}
	}
}
