<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\TrackingMigration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use WMDE\Fundraising\DonationContext\DataAccess\DatabaseDonationTrackingFetcher;
use WMDE\Fundraising\DonationContext\Domain\DonationTrackingFetcher;

class TrackingMigrationCommand {

	private const DB_CONFIG_FILE = "migrations-db.php";
	private const TRACKING_DATA_BLOB_KEY = "tracking";
	private const TRACKING_SEPARATOR = "/";

	private const BATCH_SIZE = 10000;

	private static ?DonationTrackingFetcher $donationTrackingFetcher = null;

	public static function run(): void {
		$db = self::getConnection();
		$qb = $db->createQueryBuilder();
		$qb->select( 'COUNT(*)' )
			->from( 'spenden', 'd' );
		$qb = self::addDonationQueryConditions( $qb );
		/** @var int $numDonations */
		$numDonations = $qb->executeQuery()->fetchOne();
		if ( !$numDonations ) {
			die( "No donations to migrate found." );
		}
		$migratedDonations = 0;
		$iterations = 0;
		$maxIterations = ceil( $numDonations / self::BATCH_SIZE );
		$skippedDonations = 0;
		do {
			$migratedDonationsInLastBatch = self::updateDonationBatch( $db );
			$skippedDonationsInLastBatch = $migratedDonationsInLastBatch > 0 ? self::BATCH_SIZE - $migratedDonationsInLastBatch : 0;
			$migratedDonations += $migratedDonationsInLastBatch;
			$skippedDonations += $skippedDonationsInLastBatch;
			$iterations++;
			printf(
				"Migrated %d of %d donations (%d%% done)\n",
				$migratedDonations,
				$numDonations,
				( ( $migratedDonations + $skippedDonations ) / $numDonations ) * 100
			);
		} while ( $migratedDonationsInLastBatch > 0 && $iterations <= $maxIterations );
		printf( "Migrated %d donations, skipped %d\n", $migratedDonations, $skippedDonations );
	}

	private static function updateDonationBatch( Connection $db ): int {
		$count = 0;
		foreach ( self::getDonations( $db ) as $donation ) {
			$data = self::unpackData( $donation['data'] );

			$donationId = $donation['id'];
			$tracking = explode( self::TRACKING_SEPARATOR, $data[self::TRACKING_DATA_BLOB_KEY] ?? '' );

			if ( count( $tracking ) !== 2 || $tracking[0] === '' || $tracking[1] === '' ) {
				echo "Skipping donation {$donationId} because it does not contain tracking information\n";
				continue;
			}

			$impressionCount = $data['impCount'] ?? 0;
			$bannerImpressionCount = $data['bImpCount'] ?? 0;

			$trackingId = self::getTrackingId( $db, $tracking[0], $tracking[1] );

			$qb = $db->createQueryBuilder();
			$qb->update( 'spenden' )
				->where( 'spenden.id = :donation_id' )
				->set( 'tracking_id', ':tracking_id' )
				->set( 'impression_count', ':impression_count' )
				->set( 'banner_impression_count', ':banner_impression_count' )
				->setParameter( 'donation_id', $donationId )
				->setParameter( 'tracking_id', $trackingId )
				->setParameter( 'impression_count', $impressionCount )
				->setParameter( 'banner_impression_count', $bannerImpressionCount )
				->executeQuery();
			$count++;
		}
		return $count;
	}

	private static function getConnection(): Connection {
		if ( !file_exists( self::DB_CONFIG_FILE ) ) {
			printf( "Database configuration file '%s' not found in %s\n", self::DB_CONFIG_FILE, getcwd() );
			die( 1 );
		}
		$config = include self::DB_CONFIG_FILE;
		if ( empty( $config ) ) {
			printf( "Database configuration file '%s' did not return PDO configuration data as an array\n", self::DB_CONFIG_FILE );
			die( 1 );
		}

		try {
			$conn = DriverManager::getConnection( $config );
		} catch ( Exception $e ) {
			echo $e->getMessage() . "\n";
			die( 1 );
		}

		return $conn;
	}

	/**
	 * @param Connection $db
	 *
	 * @return \Traversable<int, array{'data': string, 'id': int}>
	 * @throws Exception
	 */
	private static function getDonations( Connection $db ): \Traversable {
		$qb = $db->createQueryBuilder();
		$qb->select( 'd.id', 'd.data' )
			->from( 'spenden', 'd' )
			->setMaxResults( self::BATCH_SIZE );
		$qb = self::addDonationQueryConditions( $qb );

		$dbResult = $qb->executeQuery();

		/** @var \Traversable<int, array{'data': string, 'id': int}> $results */
		$results = $dbResult->iterateAssociative();
		return $results;
	}

	private static function addDonationQueryConditions( QueryBuilder $qb ): QueryBuilder {
		$qb->where( 'd.tracking_id = :empty_tracking_id' )
			->orWhere( 'd.tracking_id IS NULL' )
			->setMaxResults( self::BATCH_SIZE )
			->setParameter( 'empty_tracking_id', 0 );
		return $qb;
	}

	/**
	 * @param string $data
	 *
	 * @return array<string, string>
	 */
	private static function unpackData( string $data ): array {
		/** @var array<string, string> */
		return unserialize( base64_decode( $data ) );
	}

	private static function getTrackingId( Connection $db, string $campaign, string $keyword ): int {
		$donationTrackingFetcher = self::getTrackingFetcher( $db );
		return $donationTrackingFetcher->getTrackingId( $campaign, $keyword );
	}

	private static function getTrackingFetcher( Connection $db ): DonationTrackingFetcher {
		if ( self::$donationTrackingFetcher == null ) {
			self::$donationTrackingFetcher = new DatabaseDonationTrackingFetcher( $db );
		}
		return self::$donationTrackingFetcher;
	}
}
