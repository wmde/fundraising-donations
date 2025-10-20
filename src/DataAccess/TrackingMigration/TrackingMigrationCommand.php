<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\TrackingMigration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use WMDE\Fundraising\DonationContext\DataAccess\DatabaseDonationTrackingFetcher;
use WMDE\Fundraising\DonationContext\Domain\DonationTrackingFetcher;
use WMDE\Fundraising\DonationContext\DonationContextFactory;

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
		$result = new UpdateResult( 0, 0, 0 );
		do {
			$result = self::updateDonationBatch( $db, $result->lastUpdatedId );
			$migratedDonations += $result->updateCount;
			$skippedDonations += $result->skipCount;
			$iterations++;
			printf(
				"Migrated %s of %s donations (%d%% done)\n",
				number_format( $migratedDonations ),
				number_format( $numDonations ),
				( ( $migratedDonations + $skippedDonations ) / $numDonations ) * 100
			);
		} while ( $result->getNumProcessed() > 0 && $iterations <= $maxIterations );
		printf( "Migrated %s donations, skipped %s\n", number_format( $migratedDonations ), number_format( $skippedDonations ) );
	}

	private static function updateDonationBatch( Connection $db, int $idFromPreviousBatch ): UpdateResult {
		$count = 0;
		$skippedCount = 0;
		$donationId = 0;
		foreach ( self::getDonations( $db, $idFromPreviousBatch ) as $donation ) {
			$data = self::unpackData( $donation['data'] );

			$donationId = $donation['id'];
			$tracking = explode( self::TRACKING_SEPARATOR, $data[self::TRACKING_DATA_BLOB_KEY] ?? '' );

			if ( $tracking[0] === '' ) {
				error_log(
					"Skipping donation {$donationId} because it does not contain tracking information\n",
					3,
					'tracking_migration.log'
				);
				$skippedCount++;
				continue;
			}

			$impressionCount = $data['impCount'] ?? 0;
			$bannerImpressionCount = $data['bImpCount'] ?? 0;

			$trackingId = self::getTrackingId( $db, $tracking[0], $tracking[1] ?? '' );

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
		return new UpdateResult( $count, $skippedCount, $donationId );
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
	 * @return \Traversable<int, array{'data': string, 'id': int}>
	 */
	private static function getDonations( Connection $db, int $idFromPreviousBatch ): \Traversable {
		$qb = $db->createQueryBuilder();
		$qb->select( 'd.id', 'd.data' )
			->from( 'spenden', 'd' )
			->orderBy( 'd.id' )
			->setMaxResults( self::BATCH_SIZE );
		$qb = self::addDonationQueryConditions( $qb );
		$qb->andWhere( 'd.id > :previousId' )
			->setParameter( 'previousId', $idFromPreviousBatch );

		$dbResult = $qb->executeQuery();

		/** @var \Traversable<int, array{'data': string, 'id': int}> $results */
		$results = $dbResult->iterateAssociative();
		return $results;
	}

	private static function addDonationQueryConditions( QueryBuilder $qb ): QueryBuilder {
		$qb->where( 'd.tracking_id = :empty_tracking_id' )
			->orWhere( 'd.tracking_id IS NULL' )
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
			$contextFactory = new DonationContextFactory();
			$contextFactory->registerCustomTypes( $db );
			$contextFactory->registerDoctrineModerationIdentifierType( $db );
			$config = ORMSetup::createXMLMetadataConfiguration( $contextFactory->getDoctrineMappingPaths() );
			$config->enableNativeLazyObjects( true );
			$em = new EntityManager( $db, $config );
			self::$donationTrackingFetcher = new DatabaseDonationTrackingFetcher( $em );
		}
		return self::$donationTrackingFetcher;
	}
}
