<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * This migration is just for adding an index to payment_id for faster JOINs with the payment table.
 *
 * We'll run it AFTER the data migration to avoid index rebuilding on every donation change
 */
final class Version20220613145732 extends AbstractMigration {
	public function getDescription(): string {
		return 'Add index for payment id';
	}

	public function up( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->modifyColumn( 'payment_id', [ 'notnull' => true, 'unsigned' => true ] );
		$donationTable->addIndex( [ 'payment_id' ], 'm_payment_id' );
	}

	public function down( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->modifyColumn( 'payment_id', [ 'notnull' => false, 'unsigned' => true ] );
		$donationTable->dropIndex( 'm_payment_id' );
	}
}
