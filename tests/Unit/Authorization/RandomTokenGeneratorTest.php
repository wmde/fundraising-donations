<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Authorization;

use WMDE\Fundraising\DonationContext\Authorization\RandomTokenGenerator;

/**
 * @covers \WMDE\Fundraising\DonationContext\Authorization\RandomTokenGenerator
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class RandomTokenGeneratorTest extends \PHPUnit\Framework\TestCase {

	public function testGenerateTokenReturnsHexString(): void {
		$this->assertTrue(
			ctype_xdigit(
				( new RandomTokenGenerator( 10, new \DateInterval( 'PT1H' ) ) )->generateToken()
			)
		);
	}

	public function testGenerateTokenReturnsDifferentStringsOnSuccessiveCalls(): void {
		$generator = new RandomTokenGenerator( 10, new \DateInterval( 'PT1H' ) );

		$this->assertNotSame( $generator->generateToken(), $generator->generateToken() );
	}

	public function testGenerateTokenReturnsDifferentStringsForInitialCalls(): void {
		$this->assertNotSame(
			( new RandomTokenGenerator( 10, new \DateInterval( 'PT1H' ) ) )->generateToken(),
			( new RandomTokenGenerator( 10, new \DateInterval( 'PT1H' ) ) )->generateToken()
		);
	}

	public function testGenerateTokenExpiryAddsInterval(): void {
		$generator = new RandomTokenGenerator( 10, new \DateInterval( 'PT1H' ) );

		$this->assertGreaterThan(
			time(),
			$generator->generateTokenExpiry()->getTimestamp()
		);
	}

}
