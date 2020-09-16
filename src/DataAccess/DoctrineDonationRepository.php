<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments\SofortPayment as DoctrineSofortPayment;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationPayment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
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

/**
 * @license GPL-2.0-or-later
 */
class DoctrineDonationRepository implements DonationRepository {

	private $entityManager;

	public function __construct( EntityManager $entityManager ) {
		$this->entityManager = $entityManager;
	}

	public function storeDonation( Donation $donation ): void {
		if ( $donation->getId() == null ) {
			$this->insertDonation( $donation );
		} else {
			$this->updateDonation( $donation );
		}
	}

	private function insertDonation( Donation $donation ): void {
		$doctrineDonation = new DoctrineDonation();
		$this->updateDonationEntity( $doctrineDonation, $donation );

		try {
			$this->entityManager->persist( $doctrineDonation );
			$this->entityManager->flush();
		}
		catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}

		$donation->assignId( $doctrineDonation->getId() );
	}

	private function updateDonationEntity( DoctrineDonation $doctrineDonation, Donation $donation ): void {
		$doctrineDonation->setId( $donation->getId() );
		$this->updatePaymentInformation( $doctrineDonation, $donation );
		DonorFieldMapper::updateDonorInformation( $doctrineDonation, $donation->getDonor() );
		$this->updateComment( $doctrineDonation, $donation->getComment() );
		$doctrineDonation->setDonorOptsIntoNewsletter( $donation->getOptsIntoNewsletter() );
		$doctrineDonation->setDonationReceipt( $donation->getOptsIntoDonationReceipt() );

		// TODO create $this->updateExportState($doctrineDonation, $donation);
		// currently, that method is not needed because the export state is set in a dedicated
		// export script that does not use the domain model

		$doctrineDonation->encodeAndSetData(
			array_merge(
				$doctrineDonation->getDecodedData(),
				$this->getDataMap( $donation )
			)
		);
	}

	private function updatePaymentInformation( DoctrineDonation $doctrineDonation, Donation $donation ): void {
		$doctrineDonation->setStatus( $donation->getStatus() );
		$doctrineDonation->setAmount( $donation->getAmount()->getEuroString() );
		$doctrineDonation->setPaymentIntervalInMonths( $donation->getPaymentIntervalInMonths() );

		$doctrineDonation->setPaymentType( $donation->getPaymentMethodId() );
		$doctrineDonation->setBankTransferCode( self::getBankTransferCode( $donation->getPaymentMethod() ) );

		$paymentMethod = $donation->getPaymentMethod();

		if ( $paymentMethod instanceof SofortPayment ) {
			$this->updateSofortPaymentInformation( $doctrineDonation, $paymentMethod );
		}
	}

	public static function getBankTransferCode( PaymentMethod $paymentMethod ): string {
		if ( $paymentMethod instanceof BankTransferPayment ) {
			return $paymentMethod->getBankTransferCode();
		} else {
			if ( $paymentMethod instanceof SofortPayment ) {
				return $paymentMethod->getBankTransferCode();
			}
		}

		return '';
	}

	private function updateSofortPaymentInformation( DoctrineDonation $doctrineDonation, SofortPayment $paymentMethod ): void {
		$doctrineSofortPayment = $doctrineDonation->getPayment();

		if ( $doctrineSofortPayment === null ) {
			$doctrineSofortPayment = new DoctrineSofortPayment();
		}

		$doctrineSofortPayment->setConfirmedAt( $paymentMethod->getConfirmedAt() );
		$doctrineDonation->setPayment( $doctrineSofortPayment );
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
			return $this->getDataFieldsFromCreditCardData( $paymentMethod->getCreditCardData() );
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
			'ext_payment_timestamp' => $ccData->getTransactionTimestamp()->format( \DateTime::ATOM ),
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

	private function updateDonation( Donation $donation ): void {
		try {
			$doctrineDonation = $this->getDoctrineDonationById( $donation->getId() );
		}
		catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}

		if ( $doctrineDonation === null ) {
			throw new StoreDonationException();
		}

		$this->updateDonationEntity( $doctrineDonation, $donation );

		try {
			$this->entityManager->persist( $doctrineDonation );
			$this->entityManager->flush();
		}
		catch ( ORMException $ex ) {
			throw new StoreDonationException( $ex );
		}
	}

	/**
	 * @param int $id
	 *
	 * @return DoctrineDonation|null
	 * @throws ORMException
	 */
	public function getDoctrineDonationById( int $id ): ?DoctrineDonation {
		return $this->entityManager->getRepository( DoctrineDonation::class )->findOneBy(
			[
				'id' => $id,
				'deletionTime' => null
			]
		);
	}

	public function getDonationById( int $id ): ?Donation {
		try {
			$donation = $this->getDoctrineDonationById( $id );
		}
		catch ( ORMException $ex ) {
			throw new GetDonationException( $ex );
		}

		if ( $donation === null ) {
			return null;
		}

		return $this->newDonationDomainObject( $donation );
	}

	private function newDonationDomainObject( DoctrineDonation $dd ): Donation {
		$donation = new Donation(
			$dd->getId(),
			$dd->getStatus(),
			DonorFactory::createDonorFromEntity( $dd ),
			$this->getPaymentFromEntity( $dd ),
			(bool)$dd->getDonorOptsIntoNewsletter(),
			$this->getTrackingInfoFromEntity( $dd ),
			$this->getCommentFromEntity( $dd )
		);
		$donation->setOptsIntoDonationReceipt( $dd->getDonationReceipt() );
		$this->getExportState( $dd ) ? $donation->markAsExported() : null;
		return $donation;
	}

	private function getPaymentFromEntity( DoctrineDonation $dd ): DonationPayment {
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
				$payPalData->addChildPayment( $transactionId, (int)$entityId );
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

	private function getTrackingInfoFromEntity( DoctrineDonation $dd ): DonationTrackingInfo {
		$data = $dd->getDecodedData();

		$trackingInfo = new DonationTrackingInfo();

		$trackingInfo->setLayout( $data['layout'] ?? '' );
		$trackingInfo->setTotalImpressionCount( intval( $data['impCount'] ?? '0', 10 ) );
		$trackingInfo->setSingleBannerImpressionCount( intval( $data['bImpCount'] ?? '0', 10 ) );
		$trackingInfo->setTracking( $data['tracking'] ?? '' );
		$trackingInfo->setSkin( $data['skin'] ?? '' );
		$trackingInfo->setColor( $data['color'] ?? '' );
		$trackingInfo->setSource( $data['source'] ?? '' );

		return $trackingInfo->freeze()->assertNoNullFields();
	}

	private function getCommentFromEntity( DoctrineDonation $dd ): ?DonationComment {
		if ( $dd->getComment() === '' ) {
			return null;
		}

		return new DonationComment(
			$dd->getComment(),
			$dd->getIsPublic(),
			$dd->getPublicRecord()
		);
	}

	private function getExportState( DoctrineDonation $dd ): bool {
		return $dd->getDtGruen() && $dd->getDtGruen()->getTimestamp() > 0;
	}
}
