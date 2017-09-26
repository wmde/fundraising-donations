<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Unit\Infrastructure;

use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\NumberToAlphabetConverter;
use PHPUnit\Framework\TestCase;

/**
 * @covers WMDE\Fundraising\Frontend\DonationContext\Infrastructure\NumberToAlphabetConverter
 */
class NumberToAlphabetConverterTest extends TestCase {

	public function testItShouldConvertBase10ToBase16(): void {
		$converter = new NumberToAlphabetConverter( '0123456789ABCDEF' );
		$this->assertSame( '0', $converter->convertToAlphabet( 0 ) );
		$this->assertSame( '1', $converter->convertToAlphabet( 1 ) );
		$this->assertSame( '9', $converter->convertToAlphabet( 9 ) );
		$this->assertSame( 'A', $converter->convertToAlphabet( 10 ) );
		$this->assertSame( 'F', $converter->convertToAlphabet( 15 ) );
		$this->assertSame( '10', $converter->convertToAlphabet( 16 ) );
	}

	public function testItShouldConvertBase10ToBase22(): void {
		$converter = new NumberToAlphabetConverter( '0123456789ABCDEFGHIJKL' );
		$this->assertSame( '0', $converter->convertToAlphabet( 0 ) );
		$this->assertSame( '9', $converter->convertToAlphabet( 9 ) );
		$this->assertSame( 'A', $converter->convertToAlphabet( 10 ) );
		$this->assertSame( 'L', $converter->convertToAlphabet( 21 ) );
		$this->assertSame( '10', $converter->convertToAlphabet( 22 ) );
		$this->assertSame( '11', $converter->convertToAlphabet( 23 ) );
	}

	public function testItShouldUseTheCustomCharacterSetUsingItsLengthAsBase(): void {
		$converter = new NumberToAlphabetConverter( 'XEWQ' );
		$this->assertSame( 'X', $converter->convertToAlphabet( 0 ) );
		$this->assertSame( 'E', $converter->convertToAlphabet( 1 ) );
		$this->assertSame( 'W', $converter->convertToAlphabet( 2 ) );
		$this->assertSame( 'Q', $converter->convertToAlphabet( 3 ) );
		$this->assertSame( 'EX', $converter->convertToAlphabet( 4 ) );
		$this->assertSame( 'EE', $converter->convertToAlphabet( 5 ) );
		$this->assertSame( 'QWEX', $converter->convertToAlphabet( 228 ) ); // eq. base4 '3210'
	}

	public function testItShouldConvertBase16ToBase10(): void {
		$converter = new NumberToAlphabetConverter( '0123456789ABCDEF' );
		$this->assertSame( 0, $converter->convertFromAlphabet( '0' ) );
		$this->assertSame( 1, $converter->convertFromAlphabet( '1' ) );
		$this->assertSame( 9, $converter->convertFromAlphabet( '9' ) );
		$this->assertSame( 10, $converter->convertFromAlphabet( 'A' ) );
		$this->assertSame( 15, $converter->convertFromAlphabet( 'F' ) );
		$this->assertSame( 16, $converter->convertFromAlphabet( '10' ) );
	}

	public function testItShouldConvertBase22ToBase10(): void {
		$converter = new NumberToAlphabetConverter( '0123456789ABCDEFGHIJKL' );
		$this->assertSame( 0, $converter->convertFromAlphabet( '0' ) );
		$this->assertSame( 9, $converter->convertFromAlphabet( '9' ) );
		$this->assertSame( 10, $converter->convertFromAlphabet( 'A' ) );
		$this->assertSame( 21, $converter->convertFromAlphabet( 'L' ) );
		$this->assertSame( 22, $converter->convertFromAlphabet( '10' ) );
		$this->assertSame( 23, $converter->convertFromAlphabet( '11' ) );
	}

	public function testItShouldConvertFromCustomCharacterToNumber(): void {
		$converter = new NumberToAlphabetConverter( 'XEWQ' );
		$this->assertSame( 0, $converter->convertFromAlphabet( 'X' ) );
		$this->assertSame( 1, $converter->convertFromAlphabet( 'E' ) );
		$this->assertSame( 2, $converter->convertFromAlphabet( 'W' ) );
		$this->assertSame( 3, $converter->convertFromAlphabet( 'Q' ) );
		$this->assertSame( 4, $converter->convertFromAlphabet( 'EX' ) );
		$this->assertSame( 5, $converter->convertFromAlphabet( 'EE' ) );
		$this->assertSame( 228, $converter->convertFromAlphabet( 'QWEX' ) );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testAlphabetMustContainAtLeastTwoChars(): void {
		new NumberToAlphabetConverter( 'X' );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testAlphabetMustContainAtMost36Chars(): void {
		new NumberToAlphabetConverter( '123456789ABCDEFGHIJKLMNOPQRSTUVWXYZÄ' );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testAlphabetMustContainUniqueCharacters(): void {
		new NumberToAlphabetConverter( 'ABA' );
	}

	public function testStripCharactersRemovesAllCharactersNotInAlphabet(): void {
		$converter = new NumberToAlphabetConverter( 'ABCDEF' );
		$this->assertSame( 'BADCAFFEE', $converter->stripChars( '  B-AXD#COAFFIEE/[]' ) );
		$this->assertSame( 'BADCAFFEE', $converter->stripChars( 'BADCAFFEE' ) );
	}
}
