<?php

// Migration script for inserting payments from donations
// We can't use doctrine migrations because we don't want to rely on the transaction configuration of
// Doctrine migrations and this script needs transactions to run in a reasonable amount of time

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\ORMSetup;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\DonationToPaymentConverter;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\InsertingPaymentHandler;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\SequentialPaymentIdGenerator;
use WMDE\Fundraising\PaymentContext\PaymentContextFactory;

require __DIR__ . '/vendor/autoload.php';

// TODO use migrations-db.php
$config = [
	'url' => 'mysql://fundraising:INSECURE PASSWORD@database/fundraising'
];

function getStartingDonationId( Connection $db ): int {
	// subtract 1 because starting ID is exclusive
	$minId = intval( $db->fetchOne( "SELECT MIN(id) FROM spenden" ) ) - 1;
	// return 0 when minId is -1 (meaning there were no rows)
	return max( 0, $minId );
}


$db = DriverManager::getConnection( $config );
$paymentContextFactory = new PaymentContextFactory();
/** @var XmlDriver $md */
$md = $paymentContextFactory->newMappingDriver();
$paymentContextFactory->registerCustomTypes( $db );
$ormConfig = ORMSetup::createXMLMetadataConfiguration(
// TODO Use PaymentContextFactory::DOCTRINE_CLASS_MAPPING_DIRECTORY when https://github.com/wmde/fundraising-payments/pull/105 is merged
	$md->getLocator()->getPaths()
);
$entityManager = EntityManager::create( $db, $ormConfig );


// TODO after running this in prod, we need to set the autoincrement value of our id table to the last value of the generator
$paymentIdGenerator = new SequentialPaymentIdGenerator(1);
$paymentHandler = new InsertingPaymentHandler( $entityManager );
$converter = new DonationToPaymentConverter( $db, $paymentIdGenerator, $paymentHandler );

$converter->convertDonations( getStartingDonationId( $db ), DonationToPaymentConverter::CONVERT_ALL );

$paymentHandler->flushRemaining();

