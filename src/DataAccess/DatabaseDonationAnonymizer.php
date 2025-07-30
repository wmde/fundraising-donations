<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Clock\Clock;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\AnonymizationException;
use WMDE\Fundraising\DonationContext\Domain\DonationAnonymizer;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;

/**
 * This class selects and anonymizes individual donation entities.
 *
 * We're updating each individual entity, because we can't issue an UPDATE statement:
 * - The legacy address data is in the data blob
 * - When we implement more normalized address data, we need to replace the donor with a scrubbed donor entity
 */
class DatabaseDonationAnonymizer implements DonationAnonymizer {

	public function __construct(
		private readonly DonationRepository $donationRepository,
		private readonly EntityManager $entityManager,
		private readonly Clock $clock,
		private readonly \DateInterval $exportGracePeriod
	) {
	}

	public function anonymizeWithIds( int ...$donationIds ): void {
		$cutoffDate = $this->clock->now()->sub( $this->exportGracePeriod );
		foreach ( $donationIds as $id ) {
			$donation = $this->donationRepository->getDonationById( $id );
			if ( $donation === null ) {
				throw new AnonymizationException( "Could not find donation with id $id" );
			}
			$donation->scrubPersonalData( $cutoffDate );
			$this->donationRepository->storeDonation( $donation );
		}
	}

	public function anonymizeAll(): int {
		$cutoffDate = $this->clock->now()->sub( $this->exportGracePeriod );

		$qb = $this->entityManager->createQueryBuilder();
		$amountTooHighModerationId = $this->getAmountTooHighModerationReasonId();

		$qb->select( 'd' )
			->from( Donation::class, 'd' )
			->where( 'd.isScrubbed = 0' )
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNotNull( 'd.dtGruen' ),
					$qb->expr()->lte( 'd.creationTime', ':cutoffDate' )
				)
			)
			->andWhere(
				$qb->expr()->not( $qb->expr()->isMemberOf( ':moderationReason', 'd.moderationReasons' ) )
			)
			->setParameter( 'moderationReason', $amountTooHighModerationId )
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
		}
		return $count;
	}

	private function getAmountTooHighModerationReasonId(): ?ModerationReason {
		$qb = $this->entityManager->createQueryBuilder();
		$qb->select( 'mr' )
			->from( ModerationReason::class, 'mr' )
			->where( 'mr.moderationIdentifier = :moderationIdentifier' )
			->andWhere( 'mr.source = :source' )
			->setParameter( 'moderationIdentifier', ModerationIdentifier::AMOUNT_TOO_HIGH->value )
			->setParameter( 'source', 'amount' );

		/** @var array<ModerationReason> $result */
		$result = $qb->getQuery()->getResult();

		if ( count( $result ) === 0 ) {
			return null;
		}

		return $result[ 0 ];
	}
}
