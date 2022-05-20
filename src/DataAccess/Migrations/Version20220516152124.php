<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\ORMSetup;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\DonationToPaymentConverter;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\InsertingPaymentHandler;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\SequentialPaymentIdGenerator;
use WMDE\Fundraising\PaymentContext\PaymentContextFactory;

final class Version20220516152124 extends AbstractMigration {
	public function getDescription(): string {
		return 'Use new payment domain';
	}

	public function up( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE spenden CHANGE COLUMN payment_id legacy_payment_id INTEGER' );
		// We'll have to add the unique index (not FK) in the next migration, because we don't have payments yet
		$this->addSql( 'ALTER TABLE spenden ADD COLUMN payment_id INTEGER DEFAULT 0' );
	}

	public function postUp( Schema $schema ): void {
		$paymentContextFactory = new PaymentContextFactory();
		/** @var XmlDriver $md */
		$md = $paymentContextFactory->newMappingDriver();
		$paymentContextFactory->registerCustomTypes( $this->connection );
		$config = ORMSetup::createXMLMetadataConfiguration(
			// TODO Use PaymentContextFactory::DOCTRINE_CLASS_MAPPING_DIRECTORY when https://github.com/wmde/fundraising-payments/pull/105 is merged
			$md->getLocator()->getPaths()
		);
		// Normally, creating an entity manager inside a migration is dangerous, because the entity might change.
		// But this EntityManager only knows about the payment domain which is already migrated, so it's fine
		// We need it to insert the new payment entities.
		$entityManager = EntityManager::create( $this->connection, $config );

		$paymentManager = new InsertingPaymentHandler( $entityManager );
		$converter = new DonationToPaymentConverter(
			$this->connection,
			new SequentialPaymentIdGenerator( 1 ),
			$paymentManager
		);

		$converter->convertDonations( $this->getStartingDonationId(), DonationToPaymentConverter::CONVERT_ALL );

		// Insert remaining payment batch
		$paymentManager->flushRemaining();
	}

	public function down( Schema $schema ): void {
		$this->addSql( 'ALTER TABLE spenden DROP COLUMN payment_id INTEGER DEFAULT 0' );
		// TODO ask team - should we delete payments here? Should we add cascade or manually delete each payment type
		$this->addSql( 'ALTER TABLE spenden CHANGE COLUMN legacy_payment_id payment_id INTEGER' );
	}

	private function getStartingDonationId(): int {
		// subtract 1 because starting ID is exclusive
		$minId = intval( $this->connection->fetchOne( "SELECT MIN(id) FROM spenden" ) ) - 1;
		// return 0 when minId is -1 (meaning there were no rows)
		return max( 0, $minId );
	}
}
