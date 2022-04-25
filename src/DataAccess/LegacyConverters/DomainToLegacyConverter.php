<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;

class DomainToLegacyConverter {

	/**
	 * @param Donation $donation
	 * @param DoctrineDonation $doctrineDonation
	 * @param LegacyPaymentData $legacyPaymentData
	 * @param ModerationReason[] $existingModerationReasons
	 * @return DoctrineDonation
	 */
	public function convert( Donation $donation, DoctrineDonation $doctrineDonation, LegacyPaymentData $legacyPaymentData, array $existingModerationReasons ): DoctrineDonation {
		$doctrineDonation->setId( $donation->getId() );
		$this->updatePaymentInformation( $doctrineDonation, $legacyPaymentData );
		DonorFieldMapper::updateDonorInformation( $doctrineDonation, $donation->getDonor() );
		$this->updateComment( $doctrineDonation, $donation->getComment() );
		$doctrineDonation->setDonorOptsIntoNewsletter( $donation->getOptsIntoNewsletter() );
		$doctrineDonation->setDonationReceipt( $donation->getOptsIntoDonationReceipt() );
		$this->updateStatusInformation( $doctrineDonation, $donation, $legacyPaymentData );

		// TODO create $this->updateExportState($doctrineDonation, $donation);
		// currently, that method is not needed because the export state is set in a dedicated
		// export script that does not use the domain model

		$doctrineDonation->setModerationReasons( ...$this->mergeModerationReasons( $existingModerationReasons, $donation->getModerationReasons() ) );

		$doctrineDonation->encodeAndSetData(
			array_merge(
				$doctrineDonation->getDecodedData(),
				$this->getDataMap( $donation, $legacyPaymentData )
			)
		);

		return $doctrineDonation;
	}

	/**
	 * @param ModerationReason[] $existingModerationReasons
	 * @param ModerationReason[] $moderationReasonsFromDonation
	 * @return ModerationReason[]
	 */
	private function mergeModerationReasons( array $existingModerationReasons, array $moderationReasonsFromDonation ): array {
		$resultArray = [];
		foreach ( $moderationReasonsFromDonation as $moderationReason ) {
			$resultArray[(string)$moderationReason] = $moderationReason;
		}
		foreach ( $existingModerationReasons as $moderationReason ) {
			$id = (string)$moderationReason;
			if ( isset( $resultArray[$id] ) ) {
				$resultArray[$id] = $moderationReason;
			}
		}
		return array_values( $resultArray );
	}

	private function updateStatusInformation( DoctrineDonation $doctrineDonation, Donation $donation, LegacyPaymentData $legacyPaymentData ): void {
		$doctrineDonation->setStatus( $legacyPaymentData->paymentStatus );

		if ( $donation->isMarkedForModeration() && !$donation->isCancelled() ) {
			$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		}
	}

	private function updatePaymentInformation( DoctrineDonation $doctrineDonation, LegacyPaymentData $legacyPaymentData ): void {
		$doctrineDonation->setAmount( Euro::newFromCents( $legacyPaymentData->amountInEuroCents )->getEuroString() );
		$doctrineDonation->setPaymentIntervalInMonths( $legacyPaymentData->intervalInMonths );
		if ( isset( $legacyPaymentData->paymentSpecificValues['ueb_code'] ) ) {
			$doctrineDonation->setBankTransferCode( $legacyPaymentData->paymentSpecificValues['ueb_code'] );
		}

		$doctrineDonation->setPaymentType( $legacyPaymentData->paymentName );
	}

	private function updateComment( DoctrineDonation $doctrineDonation, DonationComment $comment = null ): void {
		if ( $comment === null ) {
			$doctrineDonation->setIsPublic( false );
			$doctrineDonation->setComment( '' );
			$doctrineDonation->setPublicRecord( '' );
		} else {
			$doctrineDonation->setIsPublic( $comment->isPublic() );
			$doctrineDonation->setComment( $comment->getCommentText() );
			$doctrineDonation->setPublicRecord( $comment->getAuthorDisplayName() );
		}
	}

	private function getDataMap( Donation $donation, LegacyPaymentData $legacyPaymentData ): array {
		$filteredPaymentSpecificValues = $legacyPaymentData->paymentSpecificValues;
		unset( $filteredPaymentSpecificValues['ueb_code'] );
		return array_merge(
			$this->getDataFieldsFromTrackingInfo( $donation->getTrackingInfo() ),
			$filteredPaymentSpecificValues,
			DonorFieldMapper::getPersonalDataFields( $donation->getDonor() )
		);
	}

	private function getDataFieldsFromTrackingInfo( DonationTrackingInfo $trackingInfo ): array {
		return [
			'layout' => $trackingInfo->getLayout(),
			'impCount' => $trackingInfo->getTotalImpressionCount(),
			'bImpCount' => $trackingInfo->getSingleBannerImpressionCount(),
			'tracking' => $trackingInfo->getTracking(),
			'skin' => $trackingInfo->getSkin(),
			'color' => $trackingInfo->getColor(),
			'source' => $trackingInfo->getSource(),
		];
	}
}
