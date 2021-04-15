<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments\SofortPayment as DoctrineSofortPayment;
use WMDE\Fundraising\DonationContext\DataAccess\DonorFactory;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationPayment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardTransactionData;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentWithoutAssociatedData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Infrastructure\CreditCardExpiry;

class LegacyToDomainConverter {
	public function createFromLegacyObject( DoctrineDonation $doctrineDonation ): Donation {
		$donation = new Donation(
			$doctrineDonation->getId(),
			$doctrineDonation->getStatus(),
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
		return $donation;
	}

	private function createPayment( DoctrineDonation $dd ): DonationPayment {
		return new DonationPayment(
			Euro::newFromString( $dd->getAmount() ),
			$dd->getPaymentIntervalInMonths(),
			$this->getPaymentMethodFromEntity( $dd )
		);
	}

	private function getPaymentMethodFromEntity( DoctrineDonation $dd ): PaymentMethod {
		switch ( $dd->getPaymentType() ) {
			case PaymentMethod::BANK_TRANSFER:
				return new BankTransferPayment( $dd->getBankTransferCode() );
			case PaymentMethod::DIRECT_DEBIT:
				return new DirectDebitPayment( $this->getBankDataFromEntity( $dd ) );
			case PaymentMethod::PAYPAL:
				return new PayPalPayment( $this->getPayPalDataFromEntity( $dd ) );
			case PaymentMethod::CREDIT_CARD:
				return new CreditCardPayment( $this->getCreditCardDataFromEntity( $dd ) );
			case PaymentMethod::SOFORT:
				$sofortPayment = new SofortPayment( $dd->getBankTransferCode() );
				$doctrinePayment = $dd->getPayment();
				if ( $doctrinePayment instanceof DoctrineSofortPayment ) {
					$sofortPayment->setConfirmedAt( $doctrinePayment->getConfirmedAt() );
				}

				return $sofortPayment;
		}

		return new PaymentWithoutAssociatedData( $dd->getPaymentType() );
	}

	private function getBankDataFromEntity( DoctrineDonation $dd ): BankData {
		$data = $dd->getDecodedData();

		$bankData = new BankData();
		$bankData->setIban( new Iban( $data['iban'] ?? '' ) );
		$bankData->setBic( $data['bic'] ?? '' );
		$bankData->setAccount( $data['konto'] ?? '' );
		$bankData->setBankCode( $data['blz'] ?? '' );
		$bankData->setBankName( $data['bankname'] ?? '' );

		return $bankData->freeze()->assertNoNullFields();
	}

	private function getPayPalDataFromEntity( DoctrineDonation $dd ): ?PayPalData {
		$data = $dd->getDecodedData();

		if ( !array_key_exists( 'paypal_payer_id', $data ) ) {
			return null;
		}

		$payPalData = ( new PayPalData() )
			->setPayerId( $data['paypal_payer_id'] )
			->setSubscriberId( $data['paypal_subscr_id'] ?? '' )
			->setPayerStatus( $data['paypal_payer_status'] ?? '' )
			->setAddressStatus( $data['paypal_address_status'] ?? '' )
			->setAmount( Euro::newFromString( $data['paypal_mc_gross'] ?? '0' ) )
			->setCurrencyCode( $data['paypal_mc_currency'] ?? '' )
			->setFee( Euro::newFromString( $data['paypal_mc_fee'] ?? '0' ) )
			->setSettleAmount( Euro::newFromString( $data['paypal_settle_amount'] ?? '0' ) )
			->setFirstName( $data['paypal_first_name'] ?? '' )
			->setLastName( $data['paypal_last_name'] ?? '' )
			->setAddressName( $data['paypal_address_name'] ?? '' )
			->setPaymentId( $data['ext_payment_id'] ?? '' )
			->setPaymentType( $data['ext_payment_type'] ?? '' )
			->setPaymentStatus( $data['ext_payment_status'] ?? '' )
			->setPaymentTimestamp( $data['ext_payment_timestamp'] ?? '' )
			->freeze()->assertNoNullFields();

		if ( !empty( $data['transactionIds'] ) ) {
			foreach ( $data['transactionIds'] as $transactionId => $entityId ) {
				$payPalData->addChildPayment( (string)$transactionId, (int)$entityId );
			}
		}

		return $payPalData;
	}

	private function getCreditCardDataFromEntity( DoctrineDonation $dd ): CreditCardTransactionData {
		$data = $dd->getDecodedData();

		return ( new CreditCardTransactionData() )
			->setTransactionId( $data['ext_payment_id'] ?? '' )
			->setTransactionStatus( $data['ext_payment_status'] ?? '' )
			->setTransactionTimestamp( new \DateTime( $data['ext_payment_timestamp'] ?? 'now' ) )
			->setAmount( Euro::newFromString( $data['mcp_amount'] ?? '0' ) )
			->setCustomerId( $data['ext_payment_account'] ?? '' )
			->setSessionId( $data['mcp_sessionid'] ?? '' )
			->setAuthId( $data['mcp_auth'] ?? '' )
			->setCardExpiry( CreditCardExpiry::newFromString( $data['mcp_cc_expiry_date'] ?? '' ) )
			->setTitle( $data['mcp_title'] ?? '' )
			->setCountryCode( $data['mcp_country'] ?? '' )
			->setCurrencyCode( $data['mcp_currency'] ?? '' )
			->freeze();
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
