<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

class NotificationLogSchema {

	public const TABLE_NAME = 'donation_notification_log';

	public static function createSchema( Connection $connection ): void {
		$schema = new Schema();
		$table = $schema->createTable( self::TABLE_NAME );
		$table->addColumn( 'donation_id', 'integer' );
		foreach ( $schema->toSql( $connection->getDatabasePlatform() ) as $stmt ) {
			$connection->exec( $stmt );
		}
	}

}
