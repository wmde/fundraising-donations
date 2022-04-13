<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

class DomainToLegacyConverter {
	public function convert( Donation $donation, DoctrineDonation $doctrineDonation ): DoctrineDonation {
		$doctrineDonation->setId( $donation->getId() );
		$this->updatePaymentInformation( $doctrineDonation, $donation );
		DonorFieldMapper::updateDonorInformation( $doctrineDonation, $donation->getDonor() );
		$this->updateComment( $doctrineDonation, $donation->getComment() );
		$doctrineDonation->setDonorOptsIntoNewsletter( $donation->getOptsIntoNewsletter() );
		$doctrineDonation->setDonationReceipt( $donation->getOptsIntoDonationReceipt() );
		$this->updateStatusInformation( $doctrineDonation, $donation );

		// TODO create $this->updateExportState($doctrineDonation, $donation);
		// currently, that method is not needed because the export state is set in a dedicated
		// export script that does not use the domain model

		$doctrineDonation->encodeAndSetData(
			array_merge(
				$doctrineDonation->getDecodedData(),
				$this->getDataMap( $donation )
			)
		);

		return $doctrineDonation;
	}

	private function updateStatusInformation( DoctrineDonation $doctrineDonation, Donation $donation ): void {
		// TODO get status from payment type and status
		/*
		$paymentMethod = $donation->getPaymentMethod();
		if ( $paymentMethod instanceof BankTransferPayment ) {
			$paymentStatus = DoctrineDonation::STATUS_PROMISE;
		} elseif ( $paymentMethod instanceof DirectDebitPayment ) {
			$paymentStatus = DoctrineDonation::STATUS_NEW;
		} elseif ( $paymentMethod instanceof SofortPayment ) {
			$paymentStatus = $paymentMethod->paymentCompleted() ? DoctrineDonation::STATUS_PROMISE : DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE;
		} elseif ( $paymentMethod instanceof BookablePayment ) {
			$paymentStatus = $paymentMethod->paymentCompleted() ? DoctrineDonation::STATUS_EXTERNAL_BOOKED : DoctrineDonation::STATUS_EXTERNAL_INCOMPLETE;
		} else {
			throw new \DomainException( sprintf( 'Unknown payment method "%s" - can\'t create status', get_class( $paymentMethod ) ) );
		}
		$doctrineDonation->setStatus( $paymentStatus );
		*/

		if ( $donation->isCancelled() ) {
			$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
			// returns because cancellation marker has priority over moderation marker
			return;
		}
		if ( $donation->isMarkedForModeration() ) {
			$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		}
	}

	private function updatePaymentInformation( DoctrineDonation $doctrineDonation, Donation $donation ): void {
		// TODO use GetPayment use case to get legacy data and set it here
		/*
		$doctrineDonation->setAmount( $donation->getAmount()->getEuroString() );
		$doctrineDonation->setPaymentIntervalInMonths( $donation->getPaymentIntervalInMonths() );

		$doctrineDonation->setPaymentType( $donation->getPaymentMethodId() );
		$doctrineDonation->setBankTransferCode( self::getBankTransferCode( $donation->getPaymentMethod() ) );

		$paymentMethod = $donation->getPaymentMethod();

		if ( $paymentMethod instanceof SofortPayment ) {
			$this->updateSofortPaymentInformation( $doctrineDonation, $paymentMethod );
		}
		*/
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

	private function getDataMap( Donation $donation ): array {
		return array_merge(
			$this->getDataFieldsFromTrackingInfo( $donation->getTrackingInfo() ),
			$this->getDataFieldsForPaymentData( $donation->getPayment() ),
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

	private function getDataFieldsForPaymentData( Payment $paymentMethod ): array {
		// TODO use GetPayment use case to get legacy data and set it here
		/*
		if ( $paymentMethod instanceof DirectDebitPayment ) {
			return $this->getDataFieldsFromBankData( $paymentMethod->getBankData() );
		}

		if ( $paymentMethod instanceof PayPalPayment ) {
			return $this->getDataFieldsFromPayPalData( $paymentMethod->getPayPalData() );
		}

		if ( $paymentMethod instanceof CreditCardPayment ) {
			$creditCardTransactionData = $paymentMethod->getCreditCardData();
			return $creditCardTransactionData === null ? [] : $this->getDataFieldsFromCreditCardData( $creditCardTransactionData );
		}
		*/

		return [];
	}

}
