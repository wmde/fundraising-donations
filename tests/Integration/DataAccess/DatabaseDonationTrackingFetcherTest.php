<?php

declare( strict_types = 1 );

namespace Integration\DataAccess;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DatabaseDonationTrackingFetcher;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationTracking;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

#[CoversClass( DatabaseDonationTrackingFetcher::class )]
class DatabaseDonationTrackingFetcherTest extends TestCase {

	private const string CAMPAIGN = 'Campaign McCampaigno';
	private const string KEYWORD = 'Keyword McKeywordo';

	private EntityManager $entityManager;

	private DonationTracking $existingTracking;

	public function setUp(): void {
		$this->existingTracking = new DonationTracking( strtolower( self::CAMPAIGN ), strtolower( self::KEYWORD ) );
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->entityManager = $factory->getEntityManager();
		$this->entityManager->persist( $this->existingTracking );
		$this->entityManager->flush();
	}

	public function testGetTrackingIdReturnsExistingTracking(): void {
		$trackingFetcher = new DatabaseDonationTrackingFetcher( $this->entityManager );

		$this->assertSame( 1, $trackingFetcher->getTrackingId( self::CAMPAIGN, self::KEYWORD ) );
	}

	public function testGetTrackingIdInsertsTrackingItemWhenNeeded(): void {
		$trackingFetcher = new DatabaseDonationTrackingFetcher( $this->entityManager );

		$this->assertSame( 2, $trackingFetcher->getTrackingId( 'A different Campaign', self::KEYWORD ) );
		$this->assertSame( 3, $trackingFetcher->getTrackingId( self::CAMPAIGN, 'A different keyword' ) );
		$this->assertSame( 4, $trackingFetcher->getTrackingId( 'A different Campaign', 'A different keyword' ) );
		$this->assertTrackingExistsInDatabase( 4, 'A different Campaign', 'A different keyword' );
	}

	public function testGetTrackingIdReturnsExistingTrackingFromCacheForTheSameCombinationOfCampaignAndKeyword(): void {
		$trackingFetcher = new class ( $this->entityManager ) extends DatabaseDonationTrackingFetcher {
			public int $connectionCounter = 0;

			protected function getConnection(): Connection {
				$this->connectionCounter++;
				return parent::getConnection();
			}
		};

		$trackingFetcher->getTrackingId( self::CAMPAIGN, self::KEYWORD );
		$trackingFetcher->getTrackingId( self::CAMPAIGN, self::KEYWORD );
		$trackingFetcher->getTrackingId( self::CAMPAIGN, self::KEYWORD );

		$this->assertSame( 1, $trackingFetcher->connectionCounter );
	}

	public function testGetTrackingReturnsExistingTracking(): void {
		$trackingFetcher = new DatabaseDonationTrackingFetcher( $this->entityManager );

		$tracking = $trackingFetcher->getTracking( self::CAMPAIGN, self::KEYWORD );

		$this->assertSame( $this->existingTracking, $tracking );
	}

	public function testGetTrackingInsertsTrackingItemWhenNeeded(): void {
		$trackingFetcher = new DatabaseDonationTrackingFetcher( $this->entityManager );

		$trackingFetcher->getTracking( 'A different Campaign', self::KEYWORD );
		$trackingFetcher->getTracking( self::CAMPAIGN, 'A different keyword' );
		$trackingFetcher->getTracking( 'A different Campaign', 'A different keyword' );
		$this->assertTrackingExistsInDatabase( 4, 'A different Campaign', 'A different keyword' );
	}

	private function assertTrackingExistsInDatabase( int $id, string $campaign, string $keyword ): void {
		$qb = $this->entityManager->createQueryBuilder();
		$qb->select( 't' )
			->from( DonationTracking::class, 't' )
			->where( 't.id = :id' )
			->setParameter( 'id', $id );
		/** @var DonationTracking|null $tracking */
		$tracking = $qb->getQuery()->getOneOrNullResult();

		$this->assertNotNull( $tracking, 'Entity should be in the database' );
		$this->assertSame( strtolower( $campaign ), $tracking->campaign );
		$this->assertSame( strtolower( $keyword ), $tracking->keyword );
	}
}
