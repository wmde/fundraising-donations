<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use http\Exception\RuntimeException;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Authorization\TokenSet;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DoctrineDonationAuthorizer implements DonationAuthorizer {

	private $entityManager;
	private $updateToken;
	private $accessToken;

	public function __construct( EntityManager $entityManager, string $updateToken = null, string $accessToken = null ) {
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
		try {
			$donation = $this->getDonationById( $donationId );
		}
		catch ( ORMException $ex ) {
			// TODO: might want to log failure here
			return false;
		}

		return $donation !== null
			&& $this->updateTokenMatches( $donation )
			&& $this->tokenHasNotExpired( $donation );
	}

	private function getDonationById( int $donationId ): ?Donation {
		return $this->entityManager->find( Donation::class, $donationId );
	}

	private function updateTokenMatches( Donation $donation ): bool {
		return hash_equals(
			(string)$donation->getDataObject()->getUpdateToken(),
			(string)$this->updateToken
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
		try {
			$donation = $this->getDonationById( $donationId );
		}
		catch ( ORMException $ex ) {
			// TODO: might want to log failure here
			return false;
		}

		return $donation !== null
			&& $this->updateTokenMatches( $donation );
	}

	public function canAccessDonation( int $donationId ): bool {
		try {
			$donation = $this->getDonationById( $donationId );
		}
		catch ( ORMException $ex ) {
			// TODO: might want to log failure here
			return false;
		}

		return $donation !== null
			&& $this->accessTokenMatches( $donation );
	}

	private function accessTokenMatches( Donation $donation ): bool {
		return hash_equals(
			(string)$donation->getDataObject()->getAccessToken(),
			(string)$this->accessToken
		);
	}

	public function getTokensForDonation( int $donationId ): TokenSet {
		$doctrineDonation = $this->getDonationById( $donationId );
		if ( $doctrineDonation === null ) {
			throw new GetDonationException( null );
		}
		$updateToken = $doctrineDonation->getDataObject()->getUpdateToken();
		$accessToken = $doctrineDonation->getDataObject()->getAccessToken();
		if ( $updateToken === null || $accessToken === null ) {
			throw new \UnexpectedValueException( sprintf( 'Update token / access token missing for donation %d', $donationId ) );
		}
		return new TokenSet( $updateToken, $accessToken );
	}
}
