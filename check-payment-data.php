<?php

// A script to test data quality in donations for migration to the new payments

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

$result = $converter->convertDonations();

$errors = $result->getErrors();
$warnings = $result->getWarnings();
$processedPayments = $result->getDonationCount();
$errorCount = array_reduce($errors, fn(int $acc, ResultObject $error) => $acc + $error->getItemCount(), 0 );
$warningCount = array_reduce($warnings, fn(int $acc, ResultObject $error) => $acc + $error->getItemCount(), 0 );

// TODO Move counts into AnalysisResult and get more accurate warning count by ignoring duplicate donation IDs?
//    One donation can have multiple warnings, e.g. paypal payments without payer id and timestamp

printf( "\nProcessed %d donations, with %d errors (%.2f%%) and %d warnings (%.2f%%)\n",
	$processedPayments,
	$errorCount,
	( $errorCount * 100 ) / $processedPayments,
	$warningCount,
	( $warningCount * 100 ) / $processedPayments
);

echo "\nWarnings\n";
echo "--------\n";
printf("|Error|Donations affected|Date Range|\n");
foreach($warnings as $type => $warning) {
	$dateRange = $warning->getDonationDateRange();
	$lower = new DateTimeImmutable($dateRange->getLowerBound());
	$upper = new DateTimeImmutable($dateRange->getUpperBound());
	$warningCount = $warning->getItemCount();
	$percentageOfDonations = ( $warningCount * 100 ) / $processedPayments;
	printf("|%-60s: | %d (%.2f%%) | (%s - %s) |\n", $type, $warningCount, $percentageOfDonations, $lower->format('Y-m-d'), $upper->format('Y-m-d') );
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


