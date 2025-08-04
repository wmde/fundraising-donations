<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250320141621 extends AbstractMigration {
	public function getDescription(): string {
		return 'Add two columns: street_name and house_number';
	}

	public function up( Schema $schema ): void {
		$table = $schema->getTable( 'spenden' );
		$table->addColumn( 'street_name', Types::STRING, [ 'default' => '', 'notnull' => false, 'length' => 255 ] );
		$table->addColumn( 'house_number', Types::STRING, [ 'default' => '', 'notnull' => false, 'length' => 10 ] );
	}

	public function down( Schema $schema ): void {
		$table = $schema->getTable( 'spenden' );
		$table->dropColumn( 'street_name' );
		$table->dropColumn( 'house_number' );
	}
}
