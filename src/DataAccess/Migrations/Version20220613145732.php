<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220613145732 extends AbstractMigration {
	public function getDescription(): string {
		return 'Add index for payment id';
	}

	public function up( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->changeColumn( 'payment_id', [ 'nullable' => false, 'unsigned' => true ] );
		$donationTable->addIndex( [ 'payment_id' ], 'm_payment_id' );
	}

	public function down( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->changeColumn( 'payment_id', [ 'nullable' => true, 'unsigned' => true ] );
		$donationTable->dropIndex( 'm_payment_id' );
	}
}
