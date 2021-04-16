<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;

/**
 * @license GPL-2.0-or-later
 */
class DoctrineDonationRepository implements DonationRepository {

	private EntityManager $entityManager;

	public function __construct( EntityManager $entityManager ) {
		$this->entityManager = $entityManager;
	}

	public function storeDonation( Donation $donation ): void {
		if ( $donation->getId() == null ) {
			$this->insertDonation( $donation );
		} else {
			$this->updateDonation( $donation );
		}
	}

	private function insertDonation( Donation $donation ): void {
		$converter = new DomainToLegacyConverter();
		$doctrineDonation = $converter->convert( $donation, new DoctrineDonation() );

		try {
			$this->entityManager->persist( $doctrineDonation );
			$this->entityManager->flush();
		}
		catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}

		$donation->assignId( $doctrineDonation->getId() );
	}

	private function updateDonation( Donation $donation ): void {
		try {
			$doctrineDonation = $this->getDoctrineDonationById( $donation->getId() );
		}
		catch ( GetDonationException $ex ) {
			throw new StoreDonationException( $ex );
		}

		if ( $doctrineDonation === null ) {
			throw new StoreDonationException();
		}

		$converter = new DomainToLegacyConverter();
		$doctrineDonation = $converter->convert( $donation, $doctrineDonation );

		try {
			$this->entityManager->persist( $doctrineDonation );
			$this->entityManager->flush();
		}
		catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}
	}

	private function getDoctrineDonationById( int $id ): ?DoctrineDonation {
		try {
			return $this->entityManager->find( DoctrineDonation::class, $id );
		}
		catch ( ORMException $ex ) {
			throw new GetDonationException( $ex );
		}
	}

	public function getDonationById( int $id ): ?Donation {
		$doctrineDonation = $this->getDoctrineDonationById( $id );

		if ( $doctrineDonation === null ) {
			return null;
		}

		$converter = new LegacyToDomainConverter();
		return $converter->createFromLegacyObject( $doctrineDonation );
	}
}
