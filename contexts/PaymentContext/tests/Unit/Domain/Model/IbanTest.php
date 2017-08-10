<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\PaymentContext\Tests\Unit\Domain\Model;

use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\Iban;

/**
 * @covers \WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\Iban
 *
 * @licence GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class IbanTest extends \PHPUnit\Framework\TestCase {

	const TEST_IBAN_WITH_WHITESPACE = 'DE12 5001 0517 0648 4898 90 ';
	const TEST_IBAN = 'DE12500105170648489890';
	const TEST_LOWERCASE_IBAN = 'de12500105170648489890';

	public function testGivenIbanWithWhitespace_WhitespaceIsRemoved(): void {
		$iban = new Iban( self::TEST_IBAN_WITH_WHITESPACE );
		$this->assertSame( self::TEST_IBAN, $iban->toString() );
	}

	public function testCountryCodeIsReturnedCorrectly(): void {
		$iban = new Iban( self::TEST_IBAN );
		$this->assertSame( 'DE', $iban->getCountryCode() );
	}

	public function testCountryCodeIsReturnedCorrectlyForLowercase(): void {
		$iban = new Iban( self::TEST_LOWERCASE_IBAN );
		$this->assertSame( 'DE', $iban->getCountryCode() );
	}

	public function testGivenSameIbanWithDifferentCapitalization_objectsAreEqual(): void {
		$this->assertEquals(
			new Iban( self::TEST_IBAN ),
			new Iban( self::TEST_LOWERCASE_IBAN )
		);
	}

}
