<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\CompanyName;

/**
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\CompanyName
 */
class CompanyNameTest extends TestCase {
	public function testGivenCompanyNameOnly_getFullNameReturnsCompanyName(): void {
		$companyName = new CompanyName( 'Globex Corp.' );

		$this->assertSame( 'Globex Corp.', $companyName->getFullName() );
	}

	public function testToArrayReturnsAllFields(): void {
		$companyName = new CompanyName( 'Globex Corp.' );

		$this->assertEquals(
			[
				'companyName' => 'Globex Corp.'
			],
			$companyName->toArray()
		);
	}

}
