<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationExistsChecker;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationExistsChecker
 */
class DoctrineDonationExistsCheckerTest extends TestCase {

	private const EXISTING_DONATION_ID = 1;
	private const NON_EXISTING_DONATION_ID = 99;

	private EntityManager $entityManager;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->entityManager = $factory->getEntityManager();
	}

	public function testWhenDonationExists_returnsTrue(): void {
		$checker = new DoctrineDonationExistsChecker( $this->entityManager );
		$this->givenStoredExistingDonation();

		$this->assertTrue( $checker->donationExists( self::EXISTING_DONATION_ID ) );
	}

	public function testWhenDonationDoesNotExist_returnsFalse(): void {
		$checker = new DoctrineDonationExistsChecker( $this->entityManager );
		$this->givenStoredExistingDonation();

		$this->assertFalse( $checker->donationExists( self::NON_EXISTING_DONATION_ID ) );
	}

	private function givenStoredExistingDonation(): void {
		$this->entityManager->persist( ValidDoctrineDonation::newDirectDebitDoctrineDonation() );
		$this->entityManager->flush();
	}
}
