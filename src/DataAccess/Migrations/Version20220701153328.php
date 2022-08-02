<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * This is a clean-up migration for the payment domain changes.
 *
 * You should run this when the payment has been migrated successfully
 * (2 weeks or more after the migration, when we're really sure that everything works).
 */
final class Version20220701153328 extends AbstractMigration {
	public function getDescription(): string {
		return 'Drop legacy payment tables';
	}

	public function up( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE spenden DROP COLUMN legacy_payment_id' );
		$schema->dropTable( 'donation_payment' );
		$schema->dropTable( 'donation_payment_sofort' );
	}

	public function down( Schema $schema ): void {
		$this->throwIrreversibleMigrationException( "We can't reconstruct the legacy payment IDs. Please check your backup files." );
	}
}
