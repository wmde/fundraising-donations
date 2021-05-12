<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationNotificationLog;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\NotificationLog;

class DatabaseNotificationLog implements NotificationLog {

	private const TABLE_NAME = 'donation_notification_log';

	private EntityManager $entityManager;

	public function __construct( EntityManager $connection ) {
		$this->entityManager = $connection;
	}

	public function hasSentConfirmationFor( int $donationId ): bool {
		$connection = $this->entityManager->getConnection();
		$qb = $connection->createQueryBuilder();
		$qb->select( 'COUNT(*)' )
			->from( self::TABLE_NAME )
			->where( 'donation_id = :donation_id' )
			->setParameter( 'donation_id', $donationId, Types::INTEGER );
		$result = $connection->executeQuery( $qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes() );
		return intval( $result->fetchColumn() ) > 0;
	}

	public function logConfirmationSent( int $donationId ): void {
		if ( $this->hasSentConfirmationFor( $donationId ) ) {
			return;
		}
		$donationNotificationLog = new DonationNotificationLog( $donationId );
		$this->entityManager->persist( $donationNotificationLog );
		$this->entityManager->flush();
	}
}
