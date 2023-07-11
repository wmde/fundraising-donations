<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationExistsChecker;

class DoctrineDonationExistsChecker implements DonationExistsChecker {

	public function __construct(
		private readonly EntityManager $entityManager
	) {
	}

	public function donationExists( int $donationId ): bool {
		$connection = $this->entityManager->getConnection();
		$count = $connection->prepare( 'SELECT count(*) FROM spenden WHERE id=?' )
			->executeQuery( [ $donationId ] )
			->fetchOne();

		return intval( $count ) === 1;
	}
}
