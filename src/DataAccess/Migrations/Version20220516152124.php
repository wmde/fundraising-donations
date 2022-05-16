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

		$converter = new DonationToPaymentConverter(
			$this->connection,
			new SequentialPaymentIdGenerator( 1 ),
			new InsertingPaymentHandler( $entityManager )
		);

		$converter->convertDonations();
	}

	public function down( Schema $schema ): void {
		// this down() migration is auto-generated, please modify it to your needs
	}
}
