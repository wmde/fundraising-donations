<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Clock\Clock;
use WMDE\Clock\SystemClock;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationAnonymizationMonitor;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

#[CoversClass( DoctrineDonationAnonymizationMonitor::class )]
class DoctrineDonationAnonymizationMonitorTest extends TestCase {
	private const DEFAULT_DONATION_ID = 1;

	private Connection $conn;
	private DoctrineDonationAnonymizationMonitor $monitor;
	private EntityManager $entityManager;
	private Clock $clock;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->conn = $factory->getConnection();
		$this->clock = new SystemClock();
		$this->monitor = new DoctrineDonationAnonymizationMonitor( $this->conn, $this->clock );
		$this->entityManager = $factory->getEntityManager();
	}

	public function testCountOldAbandonedModeratedDonations_ReturnsMinusOneOnError(): void {
		$throwingMonitor = new DoctrineDonationAnonymizationMonitor( $this->givenThrowingDatabaseConnection(), $this->clock );

		$this->assertEquals( -1, $throwingMonitor->countOldAbandonedModeratedDonations() );
	}

	public function testCountOldAbandonedModeratedDonations_ExcludesRecentEntries(): void {
		// create older moderated donation
		$this->insertExampleDonation( $this->newOldModeratedDonation( 1 ) );
		// create recent moderated donation
		$this->insertExampleDonation( $this->newRecentModeratedDonation( 2 ) );
		$this->assertSame( 1, $this->monitor->countOldAbandonedModeratedDonations() );
	}

	public function testCountOldAbandonedModeratedDonations_OnlyIncludesEntriesStillContainingPersonalData(): void {
		// create older moderated donation with personal data
		$this->insertExampleDonation( $this->newOldModeratedDonation( 3 ) );

		// create older moderated donation that got already exported and scrubbed (status, ...)
		$this->insertExampleDonation( $this->newOldScrubbedDonation( 4 ) );

		$this->assertSame( 1, $this->monitor->countOldAbandonedModeratedDonations() );
	}

	public function testCountOldAbandonedModeratedDonations_OnlyIncludesModeratedEntries(): void {
		// create normal unmoderated old donation
		$this->insertExampleDonation( $this->newOldNonModeratedDonation( 5 ) );

		// create moderated old donation
		$this->insertExampleDonation( $this->newOldModeratedDonation( 6 ) );

		$this->assertSame( 1, $this->monitor->countOldAbandonedModeratedDonations() );
	}

	private function givenThrowingDatabaseConnection(): Connection {
		$queryBuilderStub = $this->createStub( QueryBuilder::class );
		$queryBuilderStub->method( 'executeStatement' )
			->willThrowException( new \RuntimeException( 'Database Exception, thrown by test double' ) );

		return $this->createConfiguredStub(
			Connection::class,
			[ 'createQueryBuilder' => $queryBuilderStub ]
		);
	}

	private function newRecentModeratedDonation( int $id = self::DEFAULT_DONATION_ID ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newBankTransferDonation();
		$donation->setId( $id );
		$donation->setCreationTime( new \DateTime( $this->clock->now()->sub( new \DateInterval( 'P28D' ) )->format( 'Y-m-d H:i:s' ) ) );
		$donation->setModerationReasons( new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ) );
		$donation->setStatus( 'P' );
		return $donation;
	}

	private function newOldModeratedDonation( int $id = self::DEFAULT_DONATION_ID ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newBankTransferDonation();
		$donation->setId( $id );
		$donation->setCreationTime( new \DateTime( $this->clock->now()->sub( new \DateInterval( 'P1Y' ) )->format( 'Y-m-d H:i:s' ) ) );
		$donation->setModerationReasons( new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ) );
		$donation->setStatus( 'P' );
		return $donation;
	}

	private function newOldNonModeratedDonation( int $id = self::DEFAULT_DONATION_ID ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newBankTransferDonation();
		$donation->setId( $id );
		$donation->setCreationTime( new \DateTime( $this->clock->now()->sub( new \DateInterval( 'P1Y' ) )->format( 'Y-m-d H:i:s' ) ) );
		return $donation;
	}

	private function newOldScrubbedDonation( int $id = self::DEFAULT_DONATION_ID ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newScrubbedDonation();
		$donation->setId( $id );
		$donation->setCreationTime( new \DateTime( $this->clock->now()->sub( new \DateInterval( 'P1Y' ) )->format( 'Y-m-d H:i:s' ) ) );
		return $donation;
	}

	private function insertExampleDonation( DoctrineDonation $donationToPersist ): void {
		$this->entityManager->persist( $donationToPersist );

		$this->entityManager->flush();
	}

}
