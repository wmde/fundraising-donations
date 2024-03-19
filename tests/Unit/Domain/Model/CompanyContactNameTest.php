<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;

#[CoversClass( CompanyContactName::class )]
class CompanyContactNameTest extends TestCase {

	public function testGivenCompanyNameOnly_getFullNameReturnsCompanyName(): void {
		$companyName = new CompanyContactName( 'Scrooge and Marley', '', '', '', '' );

		$this->assertSame( 'Scrooge and Marley', $companyName->getFullName() );
	}

	public function testGivenNameWithoutSalutation_getFullNameReturnsNameWithoutSalutation(): void {
		$companyName = new CompanyContactName( 'Scrooge and Marley', 'Ebenezer', 'Scrooge', '', '' );

		$this->assertSame( 'Scrooge and Marley - Ebenezer Scrooge', $companyName->getFullName() );
	}

	public function testGivenNameWithSalutation_getFullNameReturnsNameWithoutSalutation(): void {
		$companyName = new CompanyContactName( 'Scrooge and Marley', 'Ebenezer', 'Scrooge', 'Sir', '' );

		$this->assertSame( 'Scrooge and Marley - Ebenezer Scrooge', $companyName->getFullName() );
	}

	public function testGivenNameWithTitle_getFullNameReturnsNameWithTitle(): void {
		$companyName = new CompanyContactName( 'Scrooge and Marley', 'Ebenezer', 'Scrooge', '', 'Prof. Dr.' );

		$this->assertSame( 'Scrooge and Marley - Prof. Dr. Ebenezer Scrooge', $companyName->getFullName() );
	}

	public function testGivenNameWithTitleAndSalutation_getFullNameReturnsNameWithTitle(): void {
		$companyName = new CompanyContactName( 'Scrooge and Marley', 'Ebenezer', 'Scrooge', 'Herr', 'Prof. Dr.' );

		$this->assertSame( 'Scrooge and Marley - Prof. Dr. Ebenezer Scrooge', $companyName->getFullName() );
	}

	public function testGivenCompanyNameOnly_toArrayReturnsCompanyName(): void {
		$companyName = new CompanyContactName( 'Scrooge and Marley', '', '', '', '' );

		$this->assertEquals( [ 'companyName' => 'Scrooge and Marley', ], $companyName->toArray() );
	}

	public function testToArrayReturnsAllFields(): void {
		$companyName = new CompanyContactName( 'Scrooge and Marley', 'Ebenezer', 'Scrooge', 'Herr', 'Prof. Dr.' );

		$this->assertEquals(
			[
				'companyName' => 'Scrooge and Marley',
				'salutation' => 'Herr',
				'title' => 'Prof. Dr.',
				'firstName' => 'Ebenezer',
				'lastName' => 'Scrooge'
			],
			$companyName->toArray()
		);
	}

}
