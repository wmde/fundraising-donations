<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments\SofortPayment as DoctrineSofortPayment;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\BookablePayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardTransactionData;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Infrastructure\CreditCardExpiry;

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

		$doctrineDonation->setModerationReasons( ...$donation->getModerationReasons() );

		$doctrineDonation->encodeAndSetData(
			array_merge(
				$doctrineDonation->getDecodedData(),
				$this->getDataMap( $donation )
			)
		);

		return $doctrineDonation;
	}

	private function updateStatusInformation( DoctrineDonation $doctrineDonation, Donation $donation ): void {
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
		$doctrineDonation->setAmount( $donation->getAmount()->getEuroString() );
		$doctrineDonation->setPaymentIntervalInMonths( $donation->getPaymentIntervalInMonths() );

		$doctrineDonation->setPaymentType( $donation->getPaymentMethodId() );
		$doctrineDonation->setBankTransferCode( self::getBankTransferCode( $donation->getPaymentMethod() ) );

		$paymentMethod = $donation->getPaymentMethod();

		if ( $paymentMethod instanceof SofortPayment ) {
			$this->updateSofortPaymentInformation( $doctrineDonation, $paymentMethod );
		}
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
			$this->getDataFieldsForPaymentData( $donation->getPaymentMethod() ),
			DonorFieldMapper::getPersonalDataFields( $donation->getDonor() )
		);
	}

	private static function getBankTransferCode( PaymentMethod $paymentMethod ): string {
		if ( $paymentMethod instanceof BankTransferPayment ) {
			return $paymentMethod->getBankTransferCode();
		} elseif ( $paymentMethod instanceof SofortPayment ) {
				return $paymentMethod->getBankTransferCode();
		}

		return '';
	}

	private function updateSofortPaymentInformation( DoctrineDonation $doctrineDonation, SofortPayment $paymentMethod ): void {
		/** @var ?DoctrineSofortPayment $doctrineSofortPayment */
		$doctrineSofortPayment = $doctrineDonation->getPayment();

		if ( $doctrineSofortPayment === null ) {
			$doctrineSofortPayment = new DoctrineSofortPayment();
		}

		$doctrineSofortPayment->setConfirmedAt( $paymentMethod->getConfirmedAt() );
		$doctrineDonation->setPayment( $doctrineSofortPayment );
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

	private function getDataFieldsForPaymentData( PaymentMethod $paymentMethod ): array {
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

		return [];
	}

	private function getDataFieldsFromBankData( BankData $bankData ): array {
		return [
			'iban' => $bankData->getIban()->toString(),
			'bic' => $bankData->getBic(),
			'konto' => $bankData->getAccount(),
			'blz' => $bankData->getBankCode(),
			'bankname' => $bankData->getBankName(),
		];
	}

	private function getDataFieldsFromPayPalData( PayPalData $payPalData ): array {
		return [
			'paypal_payer_id' => $payPalData->getPayerId(),
			'paypal_subscr_id' => $payPalData->getSubscriberId(),
			'paypal_payer_status' => $payPalData->getPayerStatus(),
			'paypal_address_status' => $payPalData->getAddressStatus(),
			'paypal_mc_gross' => $payPalData->getAmount()->getEuroString(),
			'paypal_mc_currency' => $payPalData->getCurrencyCode(),
			'paypal_mc_fee' => $payPalData->getFee()->getEuroString(),
			'paypal_settle_amount' => $payPalData->getSettleAmount()->getEuroString(),
			'paypal_first_name' => $payPalData->getFirstName(),
			'paypal_last_name' => $payPalData->getLastName(),
			'paypal_address_name' => $payPalData->getAddressName(),
			'ext_payment_id' => $payPalData->getPaymentId(),
			'ext_subscr_id' => $payPalData->getSubscriberId(),
			'ext_payment_type' => $payPalData->getPaymentType(),
			'ext_payment_status' => $payPalData->getPaymentStatus(),
			'ext_payment_account' => $payPalData->getPayerId(),
			'ext_payment_timestamp' => $payPalData->getPaymentTimestamp(),
			'transactionIds' => $payPalData->getAllChildPayments()
		];
	}

	private function getDataFieldsFromCreditCardData( CreditCardTransactionData $ccData ): array {
		return [
			'ext_payment_id' => $ccData->getTransactionId(),
			'ext_payment_status' => $ccData->getTransactionStatus(),
			'ext_payment_timestamp' => $ccData->getTransactionTimestamp()->format( \DateTimeInterface::ATOM ),
			'mcp_amount' => $ccData->getAmount()->getEuroString(),
			'ext_payment_account' => $ccData->getCustomerId(),
			'mcp_sessionid' => $ccData->getSessionId(),
			'mcp_auth' => $ccData->getAuthId(),
			'mcp_title' => $ccData->getTitle(),
			'mcp_country' => $ccData->getCountryCode(),
			'mcp_currency' => $ccData->getCurrencyCode(),
			'mcp_cc_expiry_date' => $this->getExpirationDateAsString( $ccData->getCardExpiry() )
		];
	}

	private function getExpirationDateAsString( CreditCardExpiry $cardExpiry = null ): string {
		if ( $cardExpiry === null ) {
			return '';
		}

		return implode( '/', [ $cardExpiry->getMonth(), $cardExpiry->getYear() ] );
	}
}
