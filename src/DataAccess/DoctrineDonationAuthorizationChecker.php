<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;

/**
 * @deprecated Use the AuthorizationChecker from the Application layer instead
 */
class DoctrineDonationAuthorizationChecker implements DonationAuthorizationChecker {

	private EntityManager $entityManager;
	private string $updateToken;
	private string $accessToken;

	public function __construct( EntityManager $entityManager, string $updateToken = '', string $accessToken = '' ) {
		$this->entityManager = $entityManager;
		$this->updateToken = $updateToken;
		$this->accessToken = $accessToken;
	}

	/**
	 * Check if donation exists, has matching token and token is not expired
	 *
	 * @param int $donationId
	 *
	 * @return bool
	 */
	public function userCanModifyDonation( int $donationId ): bool {
		$donation = $this->getDonationById( $donationId );

		return $donation !== null
			&& $this->updateTokenMatches( $donation )
			&& $this->tokenHasNotExpired( $donation );
	}

	private function getDonationById( int $donationId ): ?Donation {
		try {
			return $this->entityManager->find( Donation::class, $donationId );
		} catch ( ORMException $e ) {
			throw new GetDonationException( $e, sprintf( 'Could not get donation with id %d', $donationId ) );
		}
	}

	private function updateTokenMatches( Donation $donation ): bool {
		if ( $this->updateToken === '' ) {
			return false;
		}
		return hash_equals(
			(string)$donation->getDataObject()->getUpdateToken(),
			$this->updateToken
		);
	}

	private function tokenHasNotExpired( Donation $donation ): bool {
		$expiryTime = $donation->getDataObject()->getUpdateTokenExpiry();

		return $expiryTime !== null && strtotime( $expiryTime ) >= time();
	}

	/**
	 * Check if donation exists and has matching token
	 *
	 * @param int $donationId
	 *
	 * @return bool
	 */
	public function systemCanModifyDonation( int $donationId ): bool {
		$donation = $this->getDonationById( $donationId );

		return $donation !== null
			&& $this->updateTokenMatches( $donation );
	}

	public function canAccessDonation( int $donationId ): bool {
		$donation = $this->getDonationById( $donationId );

		return $donation !== null
			&& $this->accessTokenMatches( $donation );
	}

	private function accessTokenMatches( Donation $donation ): bool {
		if ( $this->accessToken === '' ) {
			return false;
		}
		return hash_equals(
			(string)$donation->getDataObject()->getAccessToken(),
			$this->accessToken
		);
	}
}
