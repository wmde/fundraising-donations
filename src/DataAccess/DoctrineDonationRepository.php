<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationTracking;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\DonationTrackingFetcher;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationExistsChecker;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

class DoctrineDonationRepository implements DonationRepository {

	public function __construct(
		private readonly EntityManager $entityManager,
		private readonly DonationExistsChecker $donationExistsChecker,
		private readonly GetPaymentUseCase $getPaymentUseCase,
		private readonly ModerationReasonRepository $moderationReasonRepository,
		private ?DonationTrackingFetcher $trackingFetcher = null
	) {
	}

	public function storeDonation( Donation $donation ): void {
		$existingModerationReasons = $this->moderationReasonRepository->getModerationReasonsThatAreAlreadyPersisted( ...$donation->getModerationReasons() );
		// doctrine will persist the moderation reasons that are not yet found in the database
		// and create relation entries to donation automatically
		if ( !$this->donationExistsChecker->donationExists( $donation->getId() ) ) {
			$this->insertDonation( $donation, $existingModerationReasons );
		} else {
			$this->updateDonation( $donation, $existingModerationReasons );
		}
	}

	/**
	 * @param Donation $donation
	 * @param ModerationReason[] $existingModerationReasons
	 */
	private function insertDonation( Donation $donation, array $existingModerationReasons ): void {
		$doctrineDonation = new DoctrineDonation();
		$doctrineDonation->setDonationTracking( $this->getDonationTracking( $donation ) );
		$converter = new DomainToLegacyConverter();
		$doctrineDonation = $converter->convert(
			$donation,
			$doctrineDonation,
			$this->getPaymentUseCase->getLegacyPaymentDataObject( $donation->getPaymentId() ),
			$existingModerationReasons
		);

		try {
			$this->entityManager->persist( $doctrineDonation );
			$this->entityManager->flush();
		} catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}
	}

	/**
	 * @param Donation $donation
	 * @param ModerationReason[] $existingModerationReasons
	 */
	private function updateDonation( Donation $donation, array $existingModerationReasons ): void {
		try {
			$doctrineDonation = $this->getDoctrineDonationById( $donation->getId() );
		} catch ( GetDonationException $ex ) {
			throw new StoreDonationException( $ex );
		}

		// This should never happen because we checked for existence with the donationExistsChecker in storeDonation,
		// but due to the type signature of getDoctrineDonationById we have to check again
		// @codeCoverageIgnoreStart
		if ( $doctrineDonation === null ) {
			throw new StoreDonationException( null, "Could not find donation with id '{$donation->getId()}'" );
		}
		// @codeCoverageIgnoreEnd

		$converter = new DomainToLegacyConverter();
		$doctrineDonation = $converter->convert(
			$donation,
			$doctrineDonation,
			$this->getPaymentUseCase->getLegacyPaymentDataObject( $donation->getPaymentId() ),
			$existingModerationReasons
		);

		try {
			$this->entityManager->persist( $doctrineDonation );
			$this->entityManager->flush();
		} catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}
	}

	private function getDoctrineDonationById( int $id ): ?DoctrineDonation {
		try {
			$qb = $this->entityManager->createQueryBuilder();
			$qb->select( 'd', 't' )
				->from( DoctrineDonation::class, 'd' )
				->leftJoin( 'd.donationTracking', 't' )
				->where( 'd.id = :id' )
				->setParameter( 'id', $id );
			/** @var DoctrineDonation|null */
			return $qb->getQuery()->getOneOrNullResult();
		} catch ( ORMException $ex ) {
			throw new GetDonationException( $ex, "Could not get donation with id '$id'" );
		}
	}

	public function getDonationById( int $id ): ?Donation {
		$doctrineDonation = $this->getDoctrineDonationById( $id );

		if ( $doctrineDonation === null ) {
			return null;
		}

		$converter = new LegacyToDomainConverter();
		try {
			return $converter->createFromLegacyObject( $doctrineDonation );
		} catch ( \InvalidArgumentException $ex ) {
			throw new GetDonationException( $ex, "Could not convert donation with id '$id' - " . $ex->getMessage() );
		}
	}

	private function getDonationTracking( Donation $donation ): DonationTracking {
		$trackingInfo = $donation->getTrackingInfo();
		return $this->getDonationTrackingFetcher()->getTracking( $trackingInfo->campaign, $trackingInfo->keyword );
	}

	private function getDonationTrackingFetcher(): DonationTrackingFetcher {
		if ( $this->trackingFetcher === null ) {
			$this->trackingFetcher = new DatabaseDonationTrackingFetcher( $this->entityManager );
		}
		return $this->trackingFetcher;
	}
}
