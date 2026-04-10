<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use WMDE\Clock\Clock;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\AnonymizationException;
use WMDE\Fundraising\DonationContext\Domain\DonationAnonymizer;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;

/**
 * This class selects and anonymizes individual donation entities.
 *
 * We're updating each individual entity, because we can't issue an UPDATE statement:
 * - The legacy address data is in the data blob
 * - When we implement more normalized address data, we need to replace the donor with a scrubbed donor entity
 */
class DatabaseDonationAnonymizer implements DonationAnonymizer {

	private const int BATCH_SIZE = 20;

	public function __construct(
		private readonly DonationRepository $donationRepository,
		private readonly EntityManager $entityManager,
		private readonly Clock $clock,
		private readonly \DateInterval $exportGracePeriod,
		private readonly \DateInterval $moderationGracePeriod
	) {
	}

	public function anonymizeWithIds( int ...$donationIds ): void {
		$exportCutoffDate = $this->clock->now()->sub( $this->exportGracePeriod );
		$moderationCutoffDate = $this->clock->now()->sub( $this->moderationGracePeriod );
		$counter = 0;
		foreach ( $donationIds as $id ) {
			$donation = $this->donationRepository->getDonationById( $id );
			if ( $donation === null ) {
				throw new AnonymizationException( "Could not find donation with id $id" );
			}
			$donation->scrubPersonalData( $exportCutoffDate, $moderationCutoffDate );
			$this->donationRepository->storeDonation( $donation );

			$counter++;
			if ( $counter % self::BATCH_SIZE === 0 ) {
				$this->entityManager->flush();
				$this->entityManager->clear();
			}
		}
	}

	public function anonymizeAll(): int {
		$cutoffDate = $this->clock->now()->sub( $this->exportGracePeriod );

		$qb = $this->entityManager->createQueryBuilder();
		$qb->select( 'd' )
			->from( Donation::class, 'd' )

			->where( 'd.isScrubbed = 0' )

			->andWhere(	$qb->expr()->orX(

				// scrub all already exported donations
				$qb->expr()->isNotNull( 'd.dtGruen' ),

				// scrub all deleted donations
				$qb->expr()->eq( 'd.status', ':deletedStatusFlag' ),

				// scrub "abandoned" donations with incomplete external payments (outside of grace period)
				$qb->expr()->andX(
					$qb->expr()->eq( 'd.status', ':externalIncompletePaymentStatusFlag' ),
					$qb->expr()->lte( 'd.creationTime', ':cutoffDate' )
				),

				// scrub "abandoned" donations that got flagged for moderation (outside of grace period)
				$qb->expr()->andX(
					$qb->expr()->eq( 'd.status', ':moderatedStatusFlag' ),
					$qb->expr()->lte( 'd.creationTime', ':cutoffDate' )
				)
			) )
			->setParameter( 'deletedStatusFlag', 'D', Types::STRING )
			->setParameter( 'externalIncompletePaymentStatusFlag', 'X', Types::STRING )
			->setParameter( 'moderatedStatusFlag', 'P', Types::STRING )
			->setParameter( 'cutoffDate', $cutoffDate );

		/** @var iterable<Donation> $donations */
		$donations = $qb->getQuery()->toIterable();

		$converter = new LegacyToDomainConverter();
		$count = 0;

		foreach ( $donations as $doctrineDonation ) {
			$donation = $converter->createFromLegacyObject( $doctrineDonation );
			$donation->scrubPersonalData( $cutoffDate );
			$this->donationRepository->storeDonation( $donation );
			$count++;

			if ( $count % self::BATCH_SIZE === 0 ) {
				$this->entityManager->flush();
				$this->entityManager->clear();
			}

		}
		return $count;
	}

}
