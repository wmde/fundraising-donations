<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * This migration prepares the donation table for using the new payment persistence tables.
 *
 * Since the payment data is not consistent and easily accessible, this is just modifying the structure,
 * the data migration is in {@see \WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\DonationToPaymentConverter}
 *
 * After the data migration, run {@see \WMDE\Fundraising\DonationContext\DataAccess\Migrations\Version20220613145732}
 */
final class Version20220516152124 extends AbstractMigration {
	public function getDescription(): string {
		return 'Use new payment domain for donations';
	}

	public function up( Schema $schema ): void {
		// Drop the foreign key references to be able to rename the `payment_id` column
		$this->addSql( 'ALTER TABLE spenden DROP FOREIGN KEY FK_3CBBD0454C3A3BB' );
		$this->addSql( 'DROP INDEX UNIQ_3CBBD0454C3A3BB ON spenden' );

		// Rename the old bounded-context-specific payment_id to legacy
		// We still need it for the data migration
		$this->addSql( 'ALTER TABLE spenden CHANGE COLUMN payment_id legacy_payment_id INTEGER' );

		// Add the new payment_id. Strictly speaking, this is a non-nullable foreign key, but we can't model it as such
		// We'll add the unique index in the next migration to keep the data migration fast
		$this->addSql( 'ALTER TABLE spenden ADD COLUMN payment_id INTEGER DEFAULT 0' );
	}

	public function down( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE spenden DROP COLUMN payment_id' );
		$this->addSql( 'ALTER TABLE spenden CHANGE COLUMN legacy_payment_id payment_id INTEGER' );
	}
}
