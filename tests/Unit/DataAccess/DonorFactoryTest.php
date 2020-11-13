<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFactory;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\EmailDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DonorFactory
 */
class DonorFactoryTest extends TestCase {

	public function testCreatePrivateDonor(): void {
		$donor = DonorFactory::createDonorFromEntity( ValidDoctrineDonation::newDirectDebitDoctrineDonation() );

		$this->assertInstanceOf( PersonDonor::class, $donor );
		$this->assertEquals( ValidDonation::newDonor(), $donor );
	}

	public function testCreateCompanyDonor(): void {
		$donor = DonorFactory::createDonorFromEntity( ValidDoctrineDonation::newCompanyDonation() );

		$this->assertInstanceOf( CompanyDonor::class, $donor );
		$this->assertEquals( ValidDonation::newCompanyDonor(), $donor );
	}

	public function testCreateAnonymousDonor(): void {
		$donor = DonorFactory::createDonorFromEntity( ValidDoctrineDonation::newAnonymousDonation() );

		$this->assertInstanceOf( AnonymousDonor::class, $donor );
	}

	public function testCreateEmailOnlyDonor(): void {
		$donor = DonorFactory::createDonorFromEntity( ValidDoctrineDonation::newEmailDonation() );

		$this->assertInstanceOf( EmailDonor::class, $donor );
		$this->assertEquals( ValidDonation::newEmailOnlyDonor(), $donor );
	}

	public function testCreatePrivateAnonymizedDonor(): void {
		$donor = DonorFactory::createDonorFromEntity( ValidDoctrineDonation::newAnyonymizedDonation() );

		$this->assertInstanceOf( PersonDonor::class, $donor );
		$this->assertSame( '', $donor->getName()->getFullName() );
	}

	public function testUnknownAddressTypeThrowsException(): void {
		$doctrineDonation = ValidDoctrineDonation::newAnonymousDonation();
		$doctrineDonation->encodeAndSetData( [ 'adresstyp' => 'unknown' ] );

		$this->expectException( \UnexpectedValueException::class );

		DonorFactory::createDonorFromEntity( $doctrineDonation );
	}

}
