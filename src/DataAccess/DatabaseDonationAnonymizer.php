<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\AnonymizationException;
use WMDE\Fundraising\DonationContext\Domain\DonationAnonymizer;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;

class DatabaseDonationAnonymizer implements DonationAnonymizer {

	public function __construct(
		private readonly DonationRepository $donationRepository,
		private readonly EntityManager $entityManager,
	) {
	}

	public function anonymizeAt( \DateTimeImmutable $timestamp ): int {
		// We're using individual entities with the converter because we can't issue an UPDATE statement.
		// When we have extracted the address information from the
		$converter = new LegacyToDomainConverter();
		$qb = $this->entityManager->createQueryBuilder();
		$qb->select( 'd' )
			->from( Donation::class, 'd' )
			->where( 'd.dtBackup = ?1' )
			->setParameter( 1, \DateTime::createFromImmutable( $timestamp ) );
		$count = 0;
		/** @var Donation $doctrineDonation */
		foreach ( $qb->getQuery()->toIterable() as $doctrineDonation ) {
			$donation = $converter->createFromLegacyObject( $doctrineDonation );
			$donation->scrubPersonalData();
			$this->donationRepository->storeDonation( $donation );
			$count++;
		}
		return $count;
	}

	public function anonymize( int $donationId ): void {
		$donation = $this->donationRepository->getDonationById( $donationId );
		if ( $donation === null ) {
			throw new AnonymizationException( "Could not find donation with id $donationId" );
		}
		$donation->scrubPersonalData();
		$this->donationRepository->storeDonation( $donation );
	}
}
