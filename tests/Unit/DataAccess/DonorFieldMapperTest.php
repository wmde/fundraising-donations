<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonor;

/**
 * This test is only testing the safeguards against developer error,
 * other functionality is tested in DoctrineDonationRepositoryTest.
 */
#[CoversClass( DonorFieldMapper::class )]
class DonorFieldMapperTest extends TestCase {

	public function testDonorTypeDoesNotAllowUnknownDonorClasses(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessageMatches( '/Could not determine address type/' );

		$testDonor = new FakeDonor();

		DonorFieldMapper::getPersonalDataFields( $testDonor );
	}

	public function testNameMapperProtectsAgainstUnknownFields(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessageMatches( '/Name class returned unexpected value /' );

		$specialName = new class extends Donor\Name\PersonName {

			public function __construct() {
				parent::__construct(
					'Theodosius',
					'Tester',
					'The Honourable',
					''
				);
			}

			public function toArray(): array {
				return [
					'hairColor' => 'white',
				];
			}

		};
		$validDonor = ValidDonation::newDonor();
		/**
		 * @var Donor\Address\PostalAddress $address
		 */
		$address = $validDonor->getPhysicalAddress();
		$extendedDonor = new Donor\PersonDonor( $specialName, $address, $validDonor->getEmailAddress() );

		DonorFieldMapper::getPersonalDataFields( $extendedDonor );
	}

	public function testGivenEmailOnlyDonorItConvertsAllNecessaryFields(): void {
		$fields = DonorFieldMapper::getPersonalDataFields( ValidDonation::newEmailOnlyDonor() );

		$this->assertSame( 'email', $fields['adresstyp'] );
		$this->assertSame( ValidDonation::DONOR_SALUTATION, $fields['anrede'] );
		$this->assertSame( ValidDonation::DONOR_TITLE, $fields['titel'] );
		$this->assertSame( ValidDonation::DONOR_FIRST_NAME, $fields['vorname'] );
		$this->assertSame( ValidDonation::DONOR_LAST_NAME, $fields['nachname'] );
		$this->assertSame( ValidDonation::DONOR_EMAIL_ADDRESS, $fields['email'] );
		$this->assertArrayNotHasKey( 'ort', $fields );
		$this->assertArrayNotHasKey( 'firma', $fields );
	}

	public function testGivenPersonalDonationUpdatingWithEmailOnlyKeepsCity(): void {
		$personalDonation = ValidDoctrineDonation::newPaypalDoctrineDonation();

		DonorFieldMapper::updateDonorInformation( $personalDonation, ValidDonation::newEmailOnlyDonor() );

		$this->assertSame( ValidDonation::DONOR_CITY, $personalDonation->getDonorCity() );
	}
}
