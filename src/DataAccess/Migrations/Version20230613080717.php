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
		$table = $schema->createTable( 'last_generated_donation_id' );
		$table->addColumn( 'donation_id', 'integer' );
		$table->setPrimaryKey( [ 'donation_id' ] );
	}

	public function postUp( Schema $schema ): void {
		$this->addSql(
			'INSERT INTO last_generated_donation_id (donation_id) VALUES ((SELECT MAX(id) FROM spenden))'
		);
	}

	public function down( Schema $schema ): void {
		$schema->dropTable( 'last_generated_donation_id' );
	}
}
