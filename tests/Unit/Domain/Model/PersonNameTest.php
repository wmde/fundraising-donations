<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;

#[CoversClass( PersonName::class )]
class PersonNameTest extends TestCase {

	public function testGivenNameWithoutSalutation_getFullNameReturnsNameWithoutSalutation(): void {
		$personName = new PersonName( 'Ebenezer', 'Scrooge', '', '' );

		$this->assertSame( 'Ebenezer Scrooge', $personName->getFullName() );
	}

	public function testGivenNameWithSalutation_getFullNameReturnsNameWithoutSalutation(): void {
		$personName = new PersonName( 'Ebenezer', 'Scrooge', 'Sir', '' );

		$this->assertSame( 'Ebenezer Scrooge', $personName->getFullName() );
	}

	public function testGivenNameWithTitle_getFullNameReturnsNameWithTitle(): void {
		$personName = new PersonName( 'Friedemann', 'Schulz von Thun', '', 'Prof. Dr.' );

		$this->assertSame( 'Prof. Dr. Friedemann Schulz von Thun', $personName->getFullName() );
	}

	public function testGivenNameWithTitleAndSalutation_getFullNameReturnsNameWithTitle(): void {
		$personName = new PersonName( 'Friedemann', 'Schulz von Thun', 'Herr', 'Prof. Dr.' );

		$this->assertSame( 'Prof. Dr. Friedemann Schulz von Thun', $personName->getFullName() );
	}

	public function testToArrayReturnsAllFields(): void {
		$personName = new PersonName( 'Friedemann', 'Schulz von Thun', 'Herr', 'Prof. Dr.' );

		$this->assertEquals(
			[
				'salutation' => 'Herr',
				'title' => 'Prof. Dr.',
				'firstName' => 'Friedemann',
				'lastName' => 'Schulz von Thun'
			],
			$personName->toArray()
		);
	}

}
