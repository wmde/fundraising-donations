<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;

/**
 * This test is only testing the safeguards against developer error,
 * other functionality is tested in {@see DoctrineDonationRepositoryTest}.
 */
#[CoversClass( DonorFieldMapper::class )]
class DonorFieldMapperTest extends TestCase {

	public function testNameMapperProtectsAgainstUnknownFields(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessageMatches( '/Name class returned unexpected value /' );

		$specialName = new class extends PersonName {

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
		 * @var PostalAddress $address
		 */
		$address = $validDonor->getPhysicalAddress();
		$extendedDonor = new PersonDonor( $specialName, $address, $validDonor->getEmailAddress() );

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

	public function testGivenScrubbedDonorItPassesTheOriginalDonorTypeAndTitle(): void {
		$fields = DonorFieldMapper::getPersonalDataFields( new Donor\ScrubbedDonor(
			new Donor\Name\ScrubbedName( 'Divers' ),
			DonorType::COMPANY,
			true,
			true
		) );

		$this->assertSame( 'firma', $fields['adresstyp'] );
		$this->assertSame( 'Divers', $fields['anrede'] );
	}
}
