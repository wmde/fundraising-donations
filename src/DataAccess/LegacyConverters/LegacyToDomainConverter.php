<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\DataAccess\DataReaderWithDefault;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFactory;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;

class LegacyToDomainConverter {
	public function createFromLegacyObject( DoctrineDonation $doctrineDonation ): Donation {
		if ( $doctrineDonation->getId() === null ) {
			throw new \InvalidArgumentException( "Doctrine donation ID must not be null" );
		}

		$donor = $this->getDonor( $doctrineDonation );
		$donation = new Donation(
			$doctrineDonation->getId(),
			$donor,
			$doctrineDonation->getPaymentId(),
			$this->createTrackingInfo( $doctrineDonation ),
			\DateTimeImmutable::createFromMutable( $doctrineDonation->getCreationTime() ),
			$this->createComment( $doctrineDonation )
		);
		$this->setExportStatus( $doctrineDonation, $donation );
		$this->assignCancellationAndModeration( $doctrineDonation, $donation );
		return $donation;
	}

	private function assignCancellationAndModeration( DoctrineDonation $dd, Donation $donation ): void {
		if ( $dd->getStatus() == DoctrineDonation::STATUS_CANCELLED ) {
			$donation->cancelWithoutChecks();
		}
		if ( !$dd->getModerationReasons()->isEmpty() ) {
			$donation->markForModeration( ...$dd->getModerationReasons()->toArray() );
		}
	}

	private function createTrackingInfo( DoctrineDonation $dd ): DonationTrackingInfo {
		$tracking = $dd->getDonationTracking();
		// 2025-10-15: This `if` statement should be removed when all donations have been data-migrated
		if ( $tracking === null ) {
			$data = new DataReaderWithDefault( $dd->getDecodedData() );
			return DonationTrackingInfo::newWithTrackingString(
				tracking: $data->getValue( 'tracking' ),
				totalImpressionCount: $dd->getImpressionCount(),
				singleBannerImpressionCount: $dd->getBannerImpressionCount()
			);
		}
		return new DonationTrackingInfo(
			campaign: $tracking->campaign,
			keyword: $tracking->keyword,
			totalImpressionCount: $dd->getImpressionCount(),
			singleBannerImpressionCount: $dd->getBannerImpressionCount()
		);
	}

	private function createComment( DoctrineDonation $dd ): ?DonationComment {
		if ( $dd->getComment() === '' ) {
			return null;
		}

		return new DonationComment(
			$dd->getComment(),
			$dd->getIsPublic(),
			$dd->getPublicRecord()
		);
	}

	private function getDonor( DoctrineDonation $doctrineDonation ): Donor {
		$donor = DonorFactory::createDonorFromEntity( $doctrineDonation );
		if ( $doctrineDonation->getDonationReceipt() ) {
			$donor->requireReceipt();
		} else {
			$donor->declineReceipt();
		}

		if ( $doctrineDonation->getDonorOptsIntoNewsletter() ) {
			$donor->subscribeToMailingList();
		} else {
			$donor->unsubscribeFromMailingList();
		}

		return $donor;
	}

	private function setExportStatus( DoctrineDonation $doctrineDonation, Donation $donation ): void {
		$exportDate = $doctrineDonation->getDtGruen();
		if ( $exportDate !== null && $exportDate->getTimestamp() > 0 ) {
			$donation->markAsExported( \DateTimeImmutable::createFromMutable( $exportDate ) );
		}
	}
}
