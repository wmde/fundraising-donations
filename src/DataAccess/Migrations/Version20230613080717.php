<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create table for storing the latest Donation ID
 */
final class Version20230613080717 extends AbstractMigration {

	public function getDescription(): string {
		return 'Add last_generated_donation_id table';
	}

	public function up( Schema $schema ): void {
		$donationIdTable = $schema->createTable( 'last_generated_donation_id' );
		$donationIdTable->addColumn( 'donation_id', 'integer' );
		$donationIdTable->setPrimaryKey( [ 'donation_id' ] );
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->getColumn( 'id' )->setAutoincrement( false );
	}

	public function postUp( Schema $schema ): void {
		$this->addSql(
			'INSERT INTO last_generated_donation_id (donation_id) VALUES ((SELECT MAX(id) FROM spenden))'
		);
	}

	public function down( Schema $schema ): void {
		$schema->dropTable( 'last_generated_donation_id' );
		$this->write( 'Please add back the AUTO_INCREMENT property to spenden.id. You can find instructions in ' . __FILE__ );

		// MySQL/MariaDB will fail to add back the autoincrement by calling
		// $donationTable->getColumn( 'id' )->setAutoincrement(true)
		// It fails because the change *could* affect foreign key constraint (to the moderation table)
		// In reality, that change does *not* affect the constraint, so if you really wanted to undo this migration,
		// you could run the following SQL commands:
		//
		// SET FOREIGN_KEY_CHECKS=0;
		// ALTER TABLE spenden MODIFY id int(12) NOT NULL AUTO_INCREMENT;
		// SET FOREIGN_KEY_CHECKS=1;
	}
}
