<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

/**
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\DonorType
 */
class DonorTypeTest extends TestCase {
	public function testTypesCanBeCompared(): void {
		$person = DonorType::PERSON();

		$this->assertTrue( $person->is( DonorType::PERSON() ) );
		$this->assertFalse( $person->is( DonorType::COMPANY() ) );
	}

	public function testValueObjectComparisonSucceeds(): void {
		$firstPerson = DonorType::PERSON();
		$anotherPerson = DonorType::PERSON();

		$this->assertTrue( $firstPerson == $anotherPerson );
		$this->assertEquals( $firstPerson, $anotherPerson );
	}

	public function testObjectComparisonFails(): void {
		$firstPerson = DonorType::PERSON();
		$anotherPerson = DonorType::PERSON();

		$this->assertFalse( $firstPerson === $anotherPerson );
		$this->assertNotSame( $firstPerson, $anotherPerson );
	}

	public function testInvalidDonorTypeThrowsException(): void {
		$this->expectException( \UnexpectedValueException::class );

		// @phpstan-ignore-next-line
		DonorType::HOUSE_PET();
	}

	/**
	 * @dataProvider validDonorTypeValues
	 *
	 * @param string $donorTypeValue
	 * @param DonorType $expectedDonorType
	 */
	public function testCreateDonorTypeFromString( string $donorTypeValue, DonorType $expectedDonorType ): void {
		$donorType = DonorType::make( $donorTypeValue );

		$this->assertTrue( $donorType->is( $expectedDonorType ) );
	}

	public function validDonorTypeValues(): array {
		return [
			[ 'person', DonorType::PERSON() ],
			[ 'company', DonorType::COMPANY() ],
			[ 'email', DonorType::EMAIL() ],
			[ 'anonymous', DonorType::ANONYMOUS() ],
		];
	}

	/**
	 * @dataProvider invalidDonorTypeValues
	 *
	 * @param string $donorTypeValue
	 */
	public function testCreateDonorTypeFromStringFailsForInvalidStrings( string $donorTypeValue ): void {
		$this->expectException( \UnexpectedValueException::class );

		DonorType::make( $donorTypeValue );
	}

	public function invalidDonorTypeValues(): iterable {
		yield [ '' ];
		yield [ '!!!' ];
		yield [ 'firma' ];
		yield [ 'anonym' ];
		yield [ 'dog' ];
	}

	public function testToString(): void {
		$this->assertSame( 'anonymous', sprintf( '%s', DonorType::ANONYMOUS() ) );
	}
}
