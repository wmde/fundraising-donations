<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\NotificationLog;

class DatabaseNotificationLog implements NotificationLog {

	private Connection $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	public function hasSentConfirmationFor( int $donationId ): bool {
		$qb = $this->connection->createQueryBuilder();
		$qb->select( 'COUNT(*)' )
			->from( NotificationLogSchema::TABLE_NAME )
			->where( 'donation_id = :donation_id' )
			->setParameter( 'donation_id', $donationId, Types::INTEGER );
		$result = $this->connection->executeQuery( $qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes() );
		return intval( $result->fetchColumn() ) > 0;
	}

	public function logConfirmationSent( int $donationId ): void {
		if ( $this->hasSentConfirmationFor( $donationId ) ) {
			return;
		}
		$qb = $this->connection->createQueryBuilder();
		$qb->insert( NotificationLogSchema::TABLE_NAME )
			->values(
				[ 'donation_id' => '?' ]
			)
			->setParameter( 0, $donationId );

		$this->connection->executeQuery( $qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes() );
	}
}
