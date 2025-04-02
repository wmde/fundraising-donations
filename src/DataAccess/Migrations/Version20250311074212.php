<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20250311074212 extends AbstractMigration {

	public function getDescription(): string {
		return 'Move donation tracking to new table';
	}

	public function up( Schema $schema ): void {
		$trackingTable = $schema->createTable( 'donation_tracking' );
		$id = $trackingTable->addColumn( 'id', Types::INTEGER );
		$id->setAutoincrement( true );
		$trackingTable->addColumn( 'campaign', Types::STRING, [ 'length' => 250, 'notnull' => true ] );
		$trackingTable->addColumn( 'keyword', Types::STRING, [ 'length' => 250, 'notnull' => true ] );

		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->addColumn(
			'tracking_id',
			Types::INTEGER,
			[ 'notnull' => true, 'unsigned' => true, 'default' => 0 ]
		);
		$donationTable->addIndex( [ 'tracking_id' ], 'dt_tracking_id' );
		$donationTable->addColumn( 'impression_count', Types::INTEGER, [ 'notnull' => true, 'default' => 0 ] );
		$donationTable->addColumn( 'banner_impression_count', Types::INTEGER, [ 'notnull' => true, 'default' => 0 ] );
	}

	public function down( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->dropIndex( 'dt_tracking_id' );
		$donationTable->dropColumn( 'tracking_id' );
		$donationTable->dropColumn( 'impression_count' );
		$donationTable->dropColumn( 'banner_impression_count' );

		$schema->dropTable( 'donation_tracking' );
	}
}
