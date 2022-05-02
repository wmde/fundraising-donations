<?php

// A script to test payment migration

use Doctrine\DBAL\DriverManager;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\DonationToPaymentConverter;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\ResultObject;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\SequentialPaymentIdGenerator;

require __DIR__ . '/vendor/autoload.php';

$config = [
	'url' => 'mysql://fundraising:INSECURE PASSWORD@database/fundraising'
];


$db = DriverManager::getConnection( $config );
// TODO after running this in prod, we need to set the autoincrement value of our id table to the last value of the generator
$converter = new DonationToPaymentConverter( $db, new SequentialPaymentIdGenerator(1) );

$result = $converter->convertDonations(0, 100000);

$errors = $result->getErrors();
$warnings = $result->getWarnings();
$processedPayments = $result->getDonationCount();
$errorCount = array_reduce($errors, fn(int $acc, ResultObject $error) => $acc + $error->getItemCount(), 0 );
$warningCount = array_reduce($warnings, fn(int $acc, ResultObject $error) => $acc + $error->getItemCount(), 0 );

// TODO Move counts into AnalysisResult and get more accurate warning count by ignoring duplicate donation IDs?
//    One donation can have multiple warnings, e.g. paypal payments without payer id and timestamp

printf( "Processed %d donations, with %d errors (%.2f%%) and %d warnings (%.2f%%)\n",
	$processedPayments,
	$errorCount,
	( $errorCount * 100 ) / $processedPayments,
	$warningCount,
	( $warningCount * 100 ) / $processedPayments
);

echo "\nWarnings\n";
echo "--------\n";
foreach($warnings as $type => $warning) {
	$dateRange = $warning->getDonationDateRange();
	printf("%s: %d  (%s - %s) \n", $type, $warning->getItemCount(), $dateRange->getLowerBound(), $dateRange->getUpperBound() );
}

if ( count($errors) === 0) {
	echo "\nNo errors.\n";
	return;
}

echo "\nErrors\n";
echo "------\n";
foreach($errors as $type => $error) {
	printf("%s: %d\n", $type, $error->getItemCount());
}
/** @var ResultObject $lastErrorResult */
$lastErrorResult = reset($errors);
$lastErrorClass = key($errors);
echo "$lastErrorClass\n";
print_r($lastErrorResult->getItemSample());


