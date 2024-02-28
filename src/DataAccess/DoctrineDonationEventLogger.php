<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;

class DoctrineDonationEventLogger implements DonationEventLogger {

	/**
	 * @var callable|\Closure
	 */
	private $timestampFunction;

	public function __construct( private readonly EntityManager $entityManager, ?callable $timestampFunction = null ) {
		if ( $timestampFunction === null ) {
			$this->timestampFunction = static function () {
				return date( 'Y-m-d H:i:s' );
			};
		} else {
			$this->timestampFunction = $timestampFunction;
		}
	}

	public function log( int $donationId, string $message ): void {
		try {
			/** @var ?DoctrineDonation $donation */
			$donation = $this->entityManager->find( DoctrineDonation::class, $donationId );
		} catch ( ORMException $e ) {
			throw new DonationEventLogException( 'Could not get donation', $e );
		}

		if ( $donation === null ) {
			throw new DonationEventLogException( 'Could not find donation with id ' . $donationId );
		}

		$data = $donation->getDecodedData();
		if ( empty( $data['log'] ) ) {
			$data['log'] = [];
		}
		$data['log'][call_user_func( $this->timestampFunction )] = $message;
		$donation->encodeAndSetData( $data );

		try {
			$this->entityManager->persist( $donation );
			$this->entityManager->flush();
		} catch ( ORMException $e ) {
			throw new DonationEventLogException( 'Could not store donation', $e );
		}
	}

}
