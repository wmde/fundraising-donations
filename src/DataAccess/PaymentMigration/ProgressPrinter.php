<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

class ProgressPrinter {
	private int $numDonations;
	private int $counter;
	private float $startTime;
	private float $lastOutput;
	private float $updateIntervalInSeconds;
	private \DateTimeZone $timeZone;

	public function __construct( int $updateIntervalInMilliseconds = 500 ) {
		$this->startTime = $this->lastOutput = microtime( true );
		$this->updateIntervalInSeconds = $updateIntervalInMilliseconds / 1000;
		// Using the default TS won't help much in Docker containers, but at least we attempt to print it correctly
		$this->timeZone = new \DateTimeZone( date_default_timezone_get() );
	}

	public function initialize( int $numDonations ): void {
		$this->numDonations = $numDonations;
		$this->counter = 0;
		$this->startTime = $this->lastOutput = microtime( true );
	}

	public function printProgress( int $donationId ): void {
		$this->counter++;
		$now = microtime( true );
		if ( $now - $this->lastOutput < $this->updateIntervalInSeconds ) {
			return;
		}
		$elapsed = $now - $this->startTime;
		$timePerDonation = $elapsed / $this->counter;
		$donationsToGo = $this->numDonations - $this->counter;
		$estimatedFinishSeconds = intval( $donationsToGo * $timePerDonation );
		$estimatedFinishTime = ( new \DateTimeImmutable( 'now', $this->timeZone ) )
			->modify( "+$estimatedFinishSeconds seconds" )
			->format( "H:i:s" );
		$donationsPerSecond = $this->counter / $elapsed;
		printf(
			"\r%d donations processed (%d per second), ETA %d seconds (%s). Last ID was %d",
			$this->counter,
			$donationsPerSecond,
			$estimatedFinishSeconds,
			$estimatedFinishTime,
			$donationId
		);
	}

}
