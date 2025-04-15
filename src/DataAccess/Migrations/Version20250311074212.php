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
		$id = $trackingTable->addColumn( 'id', Types::INTEGER, [ 'unsigned' => true ] );
		$id->setAutoincrement( true );
		$trackingTable->addColumn( 'campaign', Types::STRING, [ 'length' => 100, 'notnull' => true ] );
		$trackingTable->addColumn( 'keyword', Types::STRING, [ 'length' => 100, 'notnull' => true ] );
		$trackingTable->addIndex( [ 'campaign', 'keyword' ], 'dt_campaign_keyword' );
		$trackingTable->setPrimaryKey( [ 'id' ] );

		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->addColumn(
			'tracking_id',
			Types::INTEGER,
			[ 'notnull' => false ]
		);
		$donationTable->addIndex( [ 'tracking_id' ], 'd_tracking_id' );
		$donationTable->addColumn( 'impression_count', Types::INTEGER, [ 'notnull' => true, 'default' => 0 ] );
		$donationTable->addColumn( 'banner_impression_count', Types::INTEGER, [ 'notnull' => true, 'default' => 0 ] );
	}

	public function down( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->dropIndex( 'd_tracking_id' );
		$donationTable->dropColumn( 'tracking_id' );
		$donationTable->dropColumn( 'impression_count' );
		$donationTable->dropColumn( 'banner_impression_count' );

		$trackingTable = $schema->getTable( 'donation_tracking' );
		$trackingTable->dropIndex( 'dt_campaign_keyword' );
		$schema->dropTable( 'donation_tracking' );
	}
}
