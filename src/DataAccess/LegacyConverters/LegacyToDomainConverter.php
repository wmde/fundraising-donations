<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFactory;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;

class LegacyToDomainConverter {
	public function createFromLegacyObject( DoctrineDonation $doctrineDonation ): Donation {
		$donor = $this->getDonor( $doctrineDonation );
		$donation = new Donation(
			$doctrineDonation->getId(),
			$donor,
			$doctrineDonation->getPaymentId(),
			$this->createTrackingInfo( $doctrineDonation ),
			$this->createComment( $doctrineDonation )
		);
		if ( $this->entityIsExported( $doctrineDonation ) ) {
			$donation->markAsExported();
		}
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
		$data = $dd->getDecodedData();

		$trackingInfo = DonationTrackingInfo::newBlankTrackingInfo();

		$trackingInfo->setTotalImpressionCount( intval( $data['impCount'] ?? '0' ) );
		$trackingInfo->setSingleBannerImpressionCount( intval( $data['bImpCount'] ?? '0' ) );
		$trackingInfo->setTracking( $data['tracking'] ?? '' );

		return $trackingInfo->freeze()->assertNoNullFields();
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

	private function entityIsExported( DoctrineDonation $dd ): bool {
		return $dd->getDtGruen() && $dd->getDtGruen()->getTimestamp() > 0;
	}

	private function getDonor( DoctrineDonation $doctrineDonation ): Donor {
		$donor = DonorFactory::createDonorFromEntity( $doctrineDonation );
		if ( $doctrineDonation->getDonationReceipt() ) {
			$donor->requireReceipt();
		} else {
			$donor->declineReceipt();
		}

		if ( $doctrineDonation->getDonorOptsIntoNewsletter() ) {
			$donor->subscribeToNewsletter();
		} else {
			$donor->unsubscribeFromNewsletter();
		}

		return $donor;
	}
}
