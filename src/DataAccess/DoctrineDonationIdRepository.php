<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;

class DoctrineDonationIdRepository implements DonationIdRepository {
	private EntityManager $entityManager;

	public function __construct( EntityManager $entityManager ) {
		$this->entityManager = $entityManager;
	}

	public function getNewId(): int {
		$connection = $this->entityManager->getConnection();

		return $connection->transactional( function ( Connection $connection ): int {
			$this->updateDonationId( $connection );
			$result = $this->getCurrentIdResult( $connection );
			$id = $result->fetchOne();

			if ( $id === false ) {
				throw new \RuntimeException( 'The ID generator needs a row with initial donation_id set to 0.' );
			}

			return intval( $id );
		} );
	}

	private function updateDonationId( Connection $connection ): void {
		$statement = $connection->prepare( 'UPDATE last_generated_donation_id SET donation_id = donation_id + 1' );
		$statement->executeStatement();
	}

	private function getCurrentIdResult( Connection $connection ): Result {
		$statement = $connection->prepare( 'SELECT donation_id FROM last_generated_donation_id LIMIT 1' );
		return $statement->executeQuery();
	}
}
