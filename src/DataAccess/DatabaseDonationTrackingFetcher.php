<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\DBAL\Connection;
use WMDE\Fundraising\DonationContext\Domain\DonationTrackingFetcher;

class DatabaseDonationTrackingFetcher implements DonationTrackingFetcher {
	public function __construct( private readonly Connection $connection ) {
	}

	public function getTrackingId( string $campaign, string $keyword ): int {
		$qb = $this->connection->createQueryBuilder();

		$qb->select( 't.id' )
			->from( 'donation_tracking', 't' )
			->where( 't.campaign = :campaign' )
			->andWhere( 't.keyword = :keyword' )
			->setMaxResults( 1 )
			->setParameter( 'campaign', $campaign )
			->setParameter( 'keyword', $keyword );

		/** @var int|false $result */
		$result = $qb->executeQuery()->fetchOne();

		if ( $result !== false ) {
			return $result;
		}

		$qb->insert( 'donation_tracking' )
			->values( [ 'campaign' => ':campaign', 'keyword' => ':keyword' ] )
			->setParameter( 'campaign', $campaign )
			->setParameter( 'keyword', $keyword )
			->executeQuery();

		return intval( $this->connection->lastInsertId() );
	}
}
