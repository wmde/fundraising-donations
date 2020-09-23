<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper;
use WMDE\Fundraising\DonationContext\Domain\Model\Address;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;

/**
 * This test is only testing the safeguards against developer error,
 * other functionality is tested in DoctrineDonationRepositoryTest.
 *
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper
 */
class DonorFieldMapperTest extends TestCase {

	public function testDonorTypeDoesNotAllowUnknownDonorClasses(): void {
		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessageMatches( '/Could not determine address type/' );

		$testDonor = new class extends Donor\AbstractDonor {

			public function isPrivatePerson(): bool {
				return false;
			}

			public function isCompany(): bool {
				return false;
			}

			public function getDonorType(): string {
				return 'Just testing';
			}
		};

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
		$extendedDonor = new Donor\PersonDonor( $specialName, $validDonor->getPhysicalAddress(), $validDonor->getEmailAddress() );

		DonorFieldMapper::getPersonalDataFields( $extendedDonor );
	}
}
