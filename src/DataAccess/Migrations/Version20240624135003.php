<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20240624135003 extends AbstractMigration {
	public function getDescription(): string {
		return 'Add scrub status (personal data was removed from donation)';
	}

	public function up( Schema $schema ): void {
		$table = $schema->getTable( 'spenden' );
		$table->addColumn( 'is_scrubbed', Types::BOOLEAN, [ 'default' => false, 'notnull' => true ] );
	}

	public function down( Schema $schema ): void {
		$table = $schema->getTable( 'spenden' );
		$table->dropColumn( 'is_scrubbed' );
	}
}
