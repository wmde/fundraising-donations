<?php

declare( strict_types = 1 );

namespace Integration\DataAccess;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DatabaseDonationTrackingFetcher;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

#[CoversClass( DatabaseDonationTrackingFetcher::class )]
class DatabaseDonationTrackingFetcherTest extends TestCase {

	private const CAMPAIGN = 'Campaign McCampaigno';
	private const KEYWORD = 'Keyword McKeywordo';

	private Connection $connection;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->connection = $factory->getConnection();

		$this->connection->createQueryBuilder()->insert( 'donation_tracking' )
			->values( [ 'campaign' => ':campaign', 'keyword' => ':keyword' ] )
			->setParameter( 'campaign', self::CAMPAIGN )
			->setParameter( 'keyword', self::KEYWORD )
			->executeQuery();
	}

	public function testReturnsExistingTracking(): void {
		$trackingFetcher = new DatabaseDonationTrackingFetcher( $this->connection );

		$this->assertSame( 1, $trackingFetcher->getTrackingId( self::CAMPAIGN, self::KEYWORD ) );
	}

	public function testInsertsTrackingItemWhenNeeded(): void {
		$trackingFetcher = new DatabaseDonationTrackingFetcher( $this->connection );

		$this->assertSame( 2, $trackingFetcher->getTrackingId( 'A different Campaign', self::KEYWORD ) );
		$this->assertSame( 3, $trackingFetcher->getTrackingId( self::CAMPAIGN, 'A different keyword' ) );
		$this->assertSame( 4, $trackingFetcher->getTrackingId( 'A different Campaign', 'A different keyword' ) );
		$this->assertTrackingExistsInDatabase( 4, 'A different Campaign', 'A different keyword' );
	}

	private function assertTrackingExistsInDatabase( int $id, string $campaign, string $keyword ): void {
		$qb = $this->connection->createQueryBuilder();

		$qb->select( 't.campaign as campaign, t.keyword as keyword' )
			->from( 'donation_tracking', 't' )
			->where( 't.id = :id' )
			->setParameter( 'id', $id );

		$result = $qb->fetchAllAssociative();

		$this->assertCount( 1, $result );
		$this->assertSame( $campaign, $result[ 0 ][ 'campaign' ] );
		$this->assertSame( $keyword, $result[ 0 ][ 'keyword' ] );
	}
}
