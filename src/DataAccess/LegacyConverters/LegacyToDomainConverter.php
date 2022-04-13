<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFactory;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\DummyPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

class LegacyToDomainConverter {
	public function createFromLegacyObject( DoctrineDonation $doctrineDonation ): Donation {
		$donation = new Donation(
			$doctrineDonation->getId(),
			$this->convertStatus( $doctrineDonation ),
			DonorFactory::createDonorFromEntity( $doctrineDonation ),
			$this->createPayment( $doctrineDonation ),
			(bool)$doctrineDonation->getDonorOptsIntoNewsletter(),
			$this->createTrackingInfo( $doctrineDonation ),
			$this->createComment( $doctrineDonation )
		);
		$donation->setOptsIntoDonationReceipt( $doctrineDonation->getDonationReceipt() );
		if ( $this->entityIsExported( $doctrineDonation ) ) {
			$donation->markAsExported();
		}
		$this->assignCancellationAndModeration( $doctrineDonation, $donation );
		return $donation;
	}

	/**
	 * Create a new status from the payment type.
	 *
	 * This method is a violation of the Open-Closed principle because we need to touch it whenever we add new payment types.
	 * We are planning to remove the status from the Donation domain model all together,
	 * see https://phabricator.wikimedia.org/T281853
	 *
	 * @param DoctrineDonation $dd
	 *
	 * @return string
	 */
	private function convertStatus( DoctrineDonation $dd ): string {
		// TODO look at payment to see which status to set
		/*
		$paymentMethod = $this->getPaymentMethodFromEntity( $dd );
		if ( $paymentMethod instanceof BankTransferPayment ) {
			return Donation::STATUS_PROMISE;
		} elseif ( $paymentMethod instanceof DirectDebitPayment ) {
			return Donation::STATUS_NEW;
		} elseif ( $paymentMethod instanceof SofortPayment ) {
			return $paymentMethod->paymentCompleted() ? Donation::STATUS_PROMISE : Donation::STATUS_EXTERNAL_INCOMPLETE;
		} elseif ( $paymentMethod->hasExternalProvider() ) {
			return $paymentMethod->paymentCompleted() ? Donation::STATUS_EXTERNAL_BOOKED : Donation::STATUS_EXTERNAL_INCOMPLETE;
		}
		*/
		// For greater legacy compatibility (at the cost of future errors), we don't throw an exception here
		return Donation::STATUS_PROMISE;
	}

	private function assignCancellationAndModeration( DoctrineDonation $dd, Donation $donation ): void {
		if ( $dd->getStatus() == DoctrineDonation::STATUS_CANCELLED ) {
			$donation->cancelWithoutChecks();
		}
		if ( $dd->getStatus() == DoctrineDonation::STATUS_MODERATION ) {
			$donation->markForModeration();
		}
	}

	private function createPayment( DoctrineDonation $dd ): Payment {
		// TODO load payment from payment id, using payment repo
		return DummyPayment::create();
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
}
