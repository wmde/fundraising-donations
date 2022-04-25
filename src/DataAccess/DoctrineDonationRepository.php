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
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

/**
 * @license GPL-2.0-or-later
 */
class DoctrineDonationRepository implements DonationRepository {

	public function __construct(
		private EntityManager $entityManager,
		private GetPaymentUseCase $getPaymentUseCase,
		private ModerationReasonRepository $moderationReasonRepository
	) {
	}

	public function storeDonation( Donation $donation ): void {
		$existingModerationReasons = $this->moderationReasonRepository->getModerationReasonsThatAreAlreadyPersisted( ...$donation->getModerationReasons() );
		// doctrine will persist the moderation reasons that are not yet found in the database
		// and create relation entries to donation automatically
		if ( $donation->getId() == null ) {
			$this->insertDonation( $donation, $existingModerationReasons );
		} else {
			$this->updateDonation( $donation, $existingModerationReasons );
		}
	}

	private function insertDonation( Donation $donation, $existingModerationReasons ): void {
		$converter = new DomainToLegacyConverter();
		$doctrineDonation = $converter->convert(
			$donation,
			new DoctrineDonation(),
			$this->getPaymentUseCase->getLegacyPaymentDataObject( $donation->getPaymentId() ),
			$existingModerationReasons
		);

		try {
			$this->entityManager->persist( $doctrineDonation );
			$this->entityManager->flush();
		}
		catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}

		$donation->assignId( $doctrineDonation->getId() );
	}

	private function updateDonation( Donation $donation, $existingModerationReasons ): void {
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
		$doctrineDonation = $converter->convert(
			$donation,
			$doctrineDonation,
			$this->getPaymentUseCase->getLegacyPaymentDataObject( $donation->getPaymentId() ),
			$existingModerationReasons
		);

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
