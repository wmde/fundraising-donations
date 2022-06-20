<?php

// Migration script for inserting payments from donations
// We can't use doctrine migrations because we don't want to rely on the transaction configuration of
// Doctrine migrations and this script needs transactions to run in a reasonable amount of time

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\DonationPaymentIdCollection;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\DonationToPaymentConverter;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\InsertingPaymentHandler;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\PaymentIdFinder;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\RepositoryPaypalParentFinder;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\SequentialPaymentIdGenerator;
use WMDE\Fundraising\PaymentContext\PaymentContextFactory;

require __DIR__ . '/vendor/autoload.php';

// TODO find a different way to inject credentials
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
$paymentContextFactory->registerCustomTypes( $db );
$ormConfig = ORMSetup::createXMLMetadataConfiguration([ PaymentContextFactory::DOCTRINE_CLASS_MAPPING_DIRECTORY ]);
$entityManager = EntityManager::create( $db, $ormConfig );

$paymentIdCollection = new DonationPaymentIdCollection();
$paymentHandler = new InsertingPaymentHandler( $entityManager, $paymentIdCollection );
$parentFinder = new RepositoryPaypalParentFinder( $entityManager, new PaymentIdFinder( $db, $paymentIdCollection ) );
$converter = new DonationToPaymentConverter( $db, $paymentHandler, $parentFinder );

$conversionStart = microtime(true);
$result = $converter->convertDonations( getStartingDonationId( $db ), DonationToPaymentConverter::CONVERT_ALL );
$paymentHandler->flushRemaining();
$conversionEnd = microtime(true);

printf("\nTook %d seconds to convert %d donations\n",$conversionEnd-$conversionStart, $result->getDonationCount() );

$errors = $result->getErrors();
if ( count( $errors ) > 0 ) {
	echo "\nThere were errors during the data migration!\n";
	foreach($errors as $type => $error) {
		printf("%s: %d\n", $type, $error->getItemCount());
		print_r($error->getItemSample());
	}
	exit(1);
}

$unassignedPayments = $db->fetchFirstColumn("SELECT id FROM spenden WHERE payment_id = 0");
if (count($unassignedPayments) > 0) {
	echo "The following donations have unassigned payment IDs:\n";
	echo implode("\n", $unassignedPayments);
	exit(1);
}

