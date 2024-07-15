<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;
use WMDE\Fundraising\PaymentContext\Domain\PaymentType;

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
		$doctrineDonation->setPaymentId( $donation->getPaymentId() );
		$donor = $donation->getDonor();
		DonorFieldMapper::updateDonorInformation( $doctrineDonation, $donor );
		$this->updateComment( $doctrineDonation, $donation->getComment() );
		$doctrineDonation->setDonorOptsIntoNewsletter( $donor->isSubscribedToMailingList() );
		$doctrineDonation->setDonationReceipt( $donor->wantsReceipt() );
		$this->updateStatusInformation( $doctrineDonation, $donation, $legacyPaymentData );
		$this->updateExportInformation( $doctrineDonation, $donation );
		$doctrineDonation->setCreationTime( \DateTime::createFromImmutable( $donation->getDonatedOn() ) );

		$doctrineDonation->setModerationReasons( ...$this->mergeModerationReasons( $existingModerationReasons, $donation->getModerationReasons() ) );

		$doctrineDonation->encodeAndSetData(
			array_merge(
				$doctrineDonation->getDecodedData(),
				$this->getDataMap( $donation, $legacyPaymentData )
			)
		);

		$this->modifyDonationForAnonymousDonor( $donation->getDonor(), $doctrineDonation );

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

	/**
	 * Set the legacy donation status for the database entity.
	 *
	 * Some code in the Fundraising Operation Center repository still uses the status.
	 * We track the progress of removing the status in https://phabricator.wikimedia.org/T359954
	 * Last checked: 2024-05-24
	 *
	 * @deprecated
	 */
	private function updateStatusInformation( DoctrineDonation $doctrineDonation, Donation $donation, LegacyPaymentData $legacyPaymentData ): void {
		if ( $donation->isCancelled() ) {
			$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
			return;
		}

		if ( $donation->isMarkedForModeration() ) {
			$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
			return;
		}

		// Set status from payment type. This encodes a bit of payment domain knowledge into the donation domain.
		// But as long as we have the status field in the database and the Fundraising Operation Center and export
		// script uses the status, we have to have this code.
		switch ( $legacyPaymentData->paymentName ) {
			case PaymentType::DirectDebit->value:
				$doctrineDonation->setStatus( DoctrineDonation::STATUS_NEW );
				break;
			case PaymentType::BankTransfer->value:
				$doctrineDonation->setStatus( DoctrineDonation::STATUS_PROMISE );
				break;
			case PaymentType::Sofort->value:
				if ( empty( $legacyPaymentData->paymentSpecificValues['valuation_date'] ) ) {
					$doctrineDonation->setStatus( DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE );
				} else {
					$doctrineDonation->setStatus( DoctrineDonation::STATUS_PROMISE );
				}
				break;
			case PaymentType::CreditCard->value:
			case PaymentType::Paypal->value:
				if ( !empty( $legacyPaymentData->paymentSpecificValues['ext_payment_id'] ) ) {
					$doctrineDonation->setStatus( DoctrineDonation::STATUS_EXTERNAL_BOOKED );
				} else {
					$doctrineDonation->setStatus( DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE );
				}
				break;
			default:
				throw new \DomainException( 'Unknown legacy payment method: ' . $legacyPaymentData->paymentName );
		}
	}

	private function updatePaymentInformation( DoctrineDonation $doctrineDonation, LegacyPaymentData $legacyPaymentData ): void {
		$doctrineDonation->setAmount( Euro::newFromCents( $legacyPaymentData->amountInEuroCents )->getEuroString() );
		$doctrineDonation->setPaymentIntervalInMonths( $legacyPaymentData->intervalInMonths );
		if ( isset( $legacyPaymentData->paymentSpecificValues['ueb_code'] ) ) {
			$doctrineDonation->setBankTransferCode( is_string( $legacyPaymentData->paymentSpecificValues['ueb_code'] ) ? $legacyPaymentData->paymentSpecificValues['ueb_code'] : '' );
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

	/**
	 * @param Donation $donation
	 * @param LegacyPaymentData $legacyPaymentData
	 *
	 * @return array<string,mixed>
	 */
	private function getDataMap( Donation $donation, LegacyPaymentData $legacyPaymentData ): array {
		$filteredPaymentSpecificValues = $legacyPaymentData->paymentSpecificValues;
		unset( $filteredPaymentSpecificValues['ueb_code'] );
		return array_merge(
			$this->getDataFieldsFromTrackingInfo( $donation->getTrackingInfo() ),
			$filteredPaymentSpecificValues,
			DonorFieldMapper::getPersonalDataFields( $donation->getDonor() )
		);
	}

	/**
	 * @param DonationTrackingInfo $trackingInfo
	 *
	 * @return array<string,int|string>
	 */
	private function getDataFieldsFromTrackingInfo( DonationTrackingInfo $trackingInfo ): array {
		return [
			'impCount' => $trackingInfo->totalImpressionCount,
			'bImpCount' => $trackingInfo->singleBannerImpressionCount,
			'tracking' => $trackingInfo->tracking,
		];
	}

	private function modifyDonationForAnonymousDonor( Donor $donor, DoctrineDonation $doctrineDonation ): DoctrineDonation {
		if ( $donor instanceof Donor\ScrubbedDonor ) {
			$doctrineDonation->scrub();
			return DataBlobScrubber::scrubPersonalDataFromDataBlob( $doctrineDonation );
		}
		return $doctrineDonation;
	}

	private function updateExportInformation( DoctrineDonation $doctrineDonation, Donation $donation ): void {
		$exportDate = $donation->getExportDate();
		if ( $exportDate === null ) {
			$doctrineDonation->setDtGruen( null );
		} else {
			// We DON'T use setDtExport, because that field is deprecated and not in use
			$doctrineDonation->setDtGruen( \DateTime::createFromImmutable( $exportDate ) );
		}
	}
}
