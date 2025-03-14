<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\TrackingMigration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;

class TrackingMigrationCommand {
	private const DB_CONFIG_FILE = "migrations-db.php";

	public static function run(): void {
		$db = self::getConnection();

		foreach ( self::getDonations( $db ) as $donation ) {
			$data = self::unpackData( $donation[ 'data' ] );

			$donationId = $donation[ 'id' ];
			$tracking = $data[ 'tracking' ];
			$impressionCount = $data[ 'impCount' ];
			$bannerImpressionCount = $data[ 'bImpCount' ];

			$trackingId = self::getTrackingId( $db, $tracking );

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
		}
	}

	private static function getConnection(): Connection {
		if ( !file_exists( self::DB_CONFIG_FILE ) ) {
			printf( "Database configuration file '%s' not found in %s\n", self::DB_CONFIG_FILE, getcwd() );
			die( 1 );
		}
		$config = include self::DB_CONFIG_FILE;
		if ( empty( $config ) ) {
			printf( "Database configuration file '%s' did not contain configuration data\n", self::DB_CONFIG_FILE );
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

	private static function getDonations( Connection $db ): \Traversable {
		$qb = $db->createQueryBuilder();
		$qb->select( 'd.id', 'd.data' )
			->from( 'spenden', 'd' )
			->where( 'd.tracking_id = :empty_tracking_id' )
			->setMaxResults( 10000 )
			->setParameter( 'empty_tracking_id', 0 );

		$dbResult = $qb->executeQuery();
		foreach ( $dbResult->iterateAssociative() as $row ) {
			yield $row;
		}
	}

	private static function unpackData( string $data ): array {
		return unserialize( base64_decode( $data ) );
	}

	private static function getTrackingId( Connection $db, string $tracking ): int {
		$qb = $db->createQueryBuilder();
		$qb->select( 't.id' )
			->from( 'donation_tracking', 't' )
			->where( 't.tracking = :tracking' )
			->setParameter( 'tracking', $tracking );

		$result = $qb->executeQuery()->fetchAssociative();

		if( $result ) {
			return intval( $result[ 'id' ] );
		}

		$qb->insert( 'donation_tracking' )->values( [ 'tracking' => ':tracking' ] )->setParameter( 'tracking', $tracking )->executeQuery();

		return intval( $db->lastInsertId() );
	}
}
