<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetchingException;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokens;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;

/**
 * @license GPL-2.0-or-later
 */
class DoctrineDonationTokenFetcher implements DonationTokenFetcher {

	private EntityManager $entityManager;

	public function __construct( EntityManager $entityManager ) {
		$this->entityManager = $entityManager;
	}

	/**
	 * @param int $donationId
	 *
	 * @return DonationTokens
	 * @throws DonationTokenFetchingException
	 */
	public function getTokens( int $donationId ): DonationTokens {
		$donation = $this->getDonationById( $donationId );

		if ( $donation === null ) {
			throw new DonationTokenFetchingException( sprintf( 'Could not find donation with ID "%d"', $donationId ) );
		}

		try {
			return new DonationTokens(
				(string)$donation->getDataObject()->getAccessToken(),
				(string)$donation->getDataObject()->getUpdateToken()
			);
		} catch ( \UnexpectedValueException $e ) {
			throw new DonationTokenFetchingException( $e->getMessage(), $e );
		}
	}

	private function getDonationById( int $donationId ): ?Donation {
		return $this->entityManager->find( Donation::class, $donationId );
	}

}
