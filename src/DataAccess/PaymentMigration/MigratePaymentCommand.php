<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use WMDE\Fundraising\PaymentContext\PaymentContextFactory;

/**
 * Migration script for inserting payments from donations
 * We can't use Doctrine migrations because we don't want to rely on the transaction configuration of
 * Doctrine migrations and this script needs transactions to run in a reasonable amount of time
 *
 * Can be deleted when the payments are migrated successfully
 */
class MigratePaymentCommand {
	public static function run(): void {
		$db = ConnectionFactory::getConnection();
		$entityManager = self::getEntityManager( $db );

		$paymentIdCollection = new DonationPaymentIdCollection();
		$paymentHandler = new InsertingPaymentHandler( $entityManager, $paymentIdCollection );
		$parentFinder = new RepositoryPaypalParentFinder( $entityManager, new PaymentIdFinder( $db, $paymentIdCollection ) );
		$converter = new DonationToPaymentConverter( $db, $paymentHandler, $parentFinder );

		$conversionStart = microtime( true );
		$result = $converter->convertDonations( self::getStartingDonationId( $db ), DonationToPaymentConverter::CONVERT_ALL );
		$paymentHandler->flushRemaining();
		$conversionEnd = microtime( true );

		printf( "\nTook %d seconds to convert %d donations\n", $conversionEnd - $conversionStart, $result->getDonationCount() );

		$errors = $result->getErrors();
		if ( count( $errors ) > 0 ) {
			echo "\nThere were errors during the data migration!\n";
			foreach ( $errors as $type => $error ) {
				printf( "%s: %d\n", $type, $error->getItemCount() );
				print_r( $error->getItemSample() );
			}
			exit( 1 );
		}

		$unassignedPayments = $db->fetchFirstColumn( "SELECT id FROM spenden WHERE payment_id = 0" );
		if ( count( $unassignedPayments ) > 0 ) {
			echo "The following donations have unassigned payment IDs:\n";
			echo implode( "\n", $unassignedPayments );
			exit( 1 );
		}
	}

	private static function getStartingDonationId( Connection $db ): int {
		// subtract 1 because starting ID is exclusive
		$minId = intval( $db->fetchOne( "SELECT MIN(id) FROM spenden" ) ) - 1;
		// return 0 when minId is -1 (meaning there were no rows)
		return max( 0, $minId );
	}

	private static function getEntityManager( Connection $db ): EntityManager {
		$paymentContextFactory = new PaymentContextFactory();
		$paymentContextFactory->registerCustomTypes( $db );
		$ormConfig = ORMSetup::createXMLMetadataConfiguration( $paymentContextFactory->getDoctrineMappingPaths() );
		return EntityManager::create( $db, $ormConfig );
	}
}
