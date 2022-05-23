<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220516152124 extends AbstractMigration {
	public function getDescription(): string {
		return 'Use new payment domain';
	}

	public function up( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE spenden CHANGE COLUMN payment_id legacy_payment_id INTEGER' );
		// We'll have to add the unique index (not FK) in the next migration, because we don't have payments yet
		$this->addSql( 'ALTER TABLE spenden ADD COLUMN payment_id INTEGER DEFAULT 0' );
	}

	public function down( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE spenden DROP COLUMN payment_id INTEGER DEFAULT 0' );
		// TODO ask team - should we delete payments here? Should we add cascade or manually delete each payment type
		$this->addSql( 'ALTER TABLE spenden CHANGE COLUMN legacy_payment_id payment_id INTEGER' );
	}
}
