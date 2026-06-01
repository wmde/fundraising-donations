<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\DBAL\Connection;
use WMDE\Clock\Clock;

class DoctrineDonationAnonymizationMonitor implements DonationAnonymizationMonitor {

	private const string MODERATION_GRACE_PERIOD = 'P1M';
	private Connection $conn;
	private Clock $clock;

	public function __construct( Connection $conn, Clock $clock ) {
		$this->conn = $conn;
		$this->clock = $clock;
	}

	public function countOldAbandonedModeratedDonations(): int {
		$now = $this->clock->now();
		$gracePeriodDate = \DateTime::createFromImmutable( $now->sub( new \DateInterval( self::MODERATION_GRACE_PERIOD ) ) );

		$sqlQuery = "SELECT COUNT(id) as count FROM spenden s INNER JOIN donations_moderation_reasons dmr ON s.id=dmr.donation_id " .
			"WHERE ( ( s.name is not null AND s.name!='' ) OR ( s.email is not NULL AND s.email!='' ) ) AND s.dt_new < :gracePeriodDate;";
		$queryResult = $this->conn->executeQuery(
			sql: $sqlQuery,
			params: [ 'gracePeriodDate' => $gracePeriodDate->format( 'Y-m-d H:i:s' ) ]
		);

		$count = $queryResult->fetchOne();

		if ( !is_scalar( $count ) ) {
			return -1;
		}
		return intval( $count );
	}
}
