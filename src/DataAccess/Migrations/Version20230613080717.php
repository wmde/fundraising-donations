<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230613080717 extends AbstractMigration {

	public function getDescription(): string {
		return 'Add last_generated_donation_id table';
	}

	public function up( Schema $schema ): void {
		$table = $schema->createTable( 'last_generated_donation_id' );
		$table->addColumn( 'donation_id', 'integer' );
		$table->setPrimaryKey( [ 'donation_id' ] );

		$this->addSql(
			'INSERT INTO last_generated_payment_id (donation_id) VALUES ((SELECT MAX(id) + 1 FROM spenden))'
		);
	}

	public function down( Schema $schema ): void {
		$schema->dropTable( 'last_generated_donation_id' );
	}
}
