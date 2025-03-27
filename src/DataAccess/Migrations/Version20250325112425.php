<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250325112425 extends AbstractMigration {

	public function getDescription(): string {
		return 'Index is_scrubbed in the donation table';
	}

	public function up( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->addIndex( [ 'is_scrubbed' ], 'd_is_scrubbed' );
	}

	public function down( Schema $schema ): void {
		$donationTable = $schema->getTable( 'spenden' );
		$donationTable->dropIndex( 'd_is_scrubbed' );
	}
}
