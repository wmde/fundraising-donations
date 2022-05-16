<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220630195342 extends AbstractMigration {
	public function getDescription(): string {
		return 'Add moderation reasons and join table';
	}

	public function up( Schema $schema ): void {
		$reasonTable = $schema->createTable( 'donation_moderation_reason' );
		$id = $reasonTable->addColumn( 'id', 'integer' );
		$id->setAutoincrement( true );
		$reasonTable->addColumn( 'moderation_identifier', 'string', [ 'length' => 50, 'notnull' => true ] );
		$reasonTable->addColumn( 'source', 'string', [ 'length' => 32, 'notnull' => true ] );
		$reasonTable->setPrimaryKey( [ 'id' ] );
		$reasonTable->addIndex( [ 'moderation_identifier', 'source' ], 'mr_identifier' );

		$reasonJoinTable = $schema->createTable( 'donations_moderation_reasons' );
		$reasonJoinTable->addColumn( 'donation_id', 'integer', [ 'notnull' => true ] );
		$reasonJoinTable->addColumn( 'moderation_reason_id', 'integer', [ 'notnull' => true ] );
		$reasonJoinTable->setPrimaryKey( [ 'donation_id', 'moderation_reason_id' ] );
		$reasonJoinTable->addForeignKeyConstraint( 'spenden', [ 'donation_id' ], [ 'id' ] );
		$reasonJoinTable->addForeignKeyConstraint( 'donation_moderation_reason', [ 'moderation_reason_id' ], [ 'id' ] );
		$reasonJoinTable->addIndex( [ 'donation_id' ] );
		$reasonJoinTable->addIndex( [ 'moderation_reason_id' ] );
	}

	public function down( Schema $schema ): void {
		$schema->dropTable( 'donations_moderation_reasons' );
		$schema->dropTable( 'donation_moderation_reason' );
	}
}
