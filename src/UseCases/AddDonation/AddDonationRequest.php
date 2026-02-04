<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentParameters;

class AddDonationRequest {

	private DonorType $donorType;
	private string $donorFirstName = '';
	private string $donorLastName = '';
	private string $donorSalutation = '';
	private string $donorTitle = '';
	private string $donorCompany = '';

	/**
	 * @deprecated Use $donorStreetName and $donorHouseNumber instead.
	 */
	private string $donorStreetAddress = '';
	private string $donorStreetName = '';
	private string $donorHouseNumber = '';
	private string $donorPostalCode = '';
	private string $donorCity = '';
	private string $donorCountryCode = '';
	private string $donorEmailAddress = '';
	private bool $optsInToNewsletter = false;
	private PaymentParameterBuilder $paymentParameterBuilder;
	private PaymentParameters $paymentParameters;
	/**
	 * @deprecated Use trackingInfo
	 */
	private string $tracking = '';
	/**
	 * @deprecated Use trackingInfo
	 */
	private int $totalImpressionCount = 0;
	/**
	 * @deprecated Use trackingInfo
	 */
	private int $singleBannerImpressionCount = 0;
	private DonationTrackingInfo $trackingInfo;
	private bool $optsIntoDonationReceipt = true;

	/**
	 * AddDonationRequest constructor.
	 */
	public function __construct() {
		$this->donorType = DonorType::ANONYMOUS;
		$this->paymentParameterBuilder = new PaymentParameterBuilder();
		$this->trackingInfo = DonationTrackingInfo::newBlankTrackingInfo();
	}

	/**
	 * @param string $optIn
	 * @return void
	 * @deprecated Use {@see setOptsIntoNewsletter}. Remove this when Controllers in Fundraising App no longer use it
	 */
	public function setOptIn( string $optIn ): void {
		$this->setOptsIntoNewsletter( trim( $optIn ) === '1' );
	}

	/**
	 * @return Euro
	 * @deprecated Use {@see $paymentParameters}
	 */
	public function getAmount(): Euro {
		return Euro::newFromCents( $this->paymentParameters->amountInEuroCents );
	}

	/**
	 * @param Euro $amount
	 * @return void
	 * @deprecated Use {@see $paymentParameters}
	 */
	public function setAmount( Euro $amount ): void {
		$this->paymentParameterBuilder->withAmount( $amount->getEuroCents() );
		$this->paymentParameters = $this->paymentParameterBuilder->getPaymentParameters();
	}

	/**
	 * @param string $paymentType
	 * @return void
	 * @deprecated Use {@see $paymentParameters}
	 */
	public function setPaymentType( string $paymentType ): void {
		$this->paymentParameterBuilder->withPaymentType( $paymentType );
		$this->paymentParameters = $this->paymentParameterBuilder->getPaymentParameters();
	}

	/**
	 * @return int
	 * @deprecated Use {@see $paymentParameters}
	 */
	public function getInterval(): int {
		return $this->getPaymentParameters()->interval;
	}

	/**
	 * @param string $iban
	 * @deprecated Use {@see $paymentParameters}
	 */
	public function setIban( string $iban ): void {
		$this->paymentParameterBuilder->withBankData( $iban, $this->paymentParameters->bic );
		$this->paymentParameters = $this->paymentParameterBuilder->getPaymentParameters();
	}

	/**
	 * @param string $bic
	 * @return void
	 * @deprecated Use {@see $paymentParameters}
	 */
	public function setBic( string $bic ): void {
		$this->paymentParameterBuilder->withBankData( $this->paymentParameters->iban, $bic );
		$this->paymentParameters = $this->paymentParameterBuilder->getPaymentParameters();
	}

	public function getTrackingInfo(): DonationTrackingInfo {
		return $this->trackingInfo;
	}

	public function setTrackingInfo( DonationTrackingInfo $trackingInfo ): void {
		$this->trackingInfo = $trackingInfo;
		$this->tracking = $trackingInfo->getTrackingString();
		$this->totalImpressionCount = $trackingInfo->totalImpressionCount;
		$this->singleBannerImpressionCount = $trackingInfo->singleBannerImpressionCount;
	}

	/**
	 * @deprecated use {@see self::getTrackingInfo()}
	 */
	public function getTracking(): string {
		return $this->tracking;
	}

	/**
	 * @deprecated use {@see self::setTrackingInfo()}
	 */
	public function setTracking( string $tracking ): void {
		$this->tracking = trim( $tracking );
		$this->trackingInfo = DonationTrackingInfo::newWithTrackingString(
			$tracking,
			$this->trackingInfo->totalImpressionCount,
			$this->trackingInfo->singleBannerImpressionCount
		);
	}

	/**
	 * @deprecated use {@see self::getTrackingInfo()}
	 */
	public function getTotalImpressionCount(): int {
		return $this->totalImpressionCount;
	}

	/**
	 * @deprecated use {@see self::setTrackingInfo()}
	 */
	public function setTotalImpressionCount( int $totalImpressionCount ): void {
		$this->totalImpressionCount = $totalImpressionCount;
		$this->trackingInfo = new DonationTrackingInfo(
			$this->trackingInfo->campaign,
			$this->trackingInfo->keyword,
			$totalImpressionCount,
			$this->trackingInfo->singleBannerImpressionCount
		);
	}

	/**
	 * @deprecated use {@see self::getTrackingInfo()}
	 */
	public function getSingleBannerImpressionCount(): int {
		return $this->singleBannerImpressionCount;
	}

	/**
	 * @deprecated use {@see self::setTrackingInfo()}
	 */
	public function setSingleBannerImpressionCount( int $singleBannerImpressionCount ): void {
		$this->singleBannerImpressionCount = $singleBannerImpressionCount;
		$this->trackingInfo = new DonationTrackingInfo(
			$this->trackingInfo->campaign,
			$this->trackingInfo->keyword,
			$this->trackingInfo->totalImpressionCount,
			$singleBannerImpressionCount
		);
	}

	public function getDonorType(): DonorType {
		return $this->donorType;
	}

	public function setDonorType( DonorType $donorType ): void {
		$this->donorType = $donorType;
	}

	public function getDonorFirstName(): string {
		return $this->donorFirstName;
	}

	public function setDonorFirstName( string $donorFirstName ): void {
		$this->donorFirstName = trim( $donorFirstName );
	}

	public function getDonorLastName(): string {
		return $this->donorLastName;
	}

	public function setDonorLastName( string $donorLastName ): void {
		$this->donorLastName = trim( $donorLastName );
	}

	public function getDonorSalutation(): string {
		return $this->donorSalutation;
	}

	public function setDonorSalutation( string $donorSalutation ): void {
		$this->donorSalutation = trim( $donorSalutation );
	}

	public function getDonorTitle(): string {
		return $this->donorTitle;
	}

	public function setDonorTitle( string $donorTitle ): void {
		$this->donorTitle = trim( $donorTitle );
	}

	public function getDonorCompany(): string {
		return $this->donorCompany;
	}

	public function setDonorCompany( string $donorCompany ): void {
		$this->donorCompany = trim( $donorCompany );
	}

	/**
	 * @deprecated Use getDonorStreetName() and getDonorHouseNumber() instead.
	 */
	public function getDonorStreetAddress(): string {
		return $this->donorStreetAddress;
	}

	/**
	 * @deprecated Use setDonorStreetName() and setDonorHouseNumber() instead.
	 */
	public function setDonorStreetAddress( string $donorStreetAddress ): void {
		$this->donorStreetAddress = trim( $donorStreetAddress );
	}

	public function getDonorStreetName(): string {
		return $this->donorStreetName;
	}

	public function setDonorStreetName( string $donorStreetName ): void {
		$this->donorStreetName = trim( $donorStreetName );
	}

	public function getDonorHouseNumber(): string {
		return $this->donorHouseNumber;
	}

	public function setDonorHouseNumber( string $donorHouseNumber ): void {
		$this->donorHouseNumber = trim( $donorHouseNumber );
	}

	public function getDonorPostalCode(): string {
		return $this->donorPostalCode;
	}

	public function setDonorPostalCode( string $donorPostalCode ): void {
		$this->donorPostalCode = trim( $donorPostalCode );
	}

	public function getDonorCity(): string {
		return $this->donorCity;
	}

	public function setDonorCity( string $donorCity ): void {
		$this->donorCity = trim( $donorCity );
	}

	public function getDonorCountryCode(): string {
		return $this->donorCountryCode;
	}

	public function setDonorCountryCode( string $donorCountryCode ): void {
		$this->donorCountryCode = trim( $donorCountryCode );
	}

	public function getDonorEmailAddress(): string {
		return $this->donorEmailAddress;
	}

	public function setDonorEmailAddress( string $donorEmailAddress ): void {
		$this->donorEmailAddress = trim( $donorEmailAddress );
	}

	public function donorIsAnonymous(): bool {
		return $this->getDonorType() === DonorType::ANONYMOUS;
	}

	public function donorIsEmailOnly(): bool {
		return $this->getDonorType() === DonorType::EMAIL;
	}

	public function setOptsIntoDonationReceipt( bool $optIn ): void {
		$this->optsIntoDonationReceipt = $optIn;
	}

	public function getOptsIntoDonationReceipt(): bool {
		return $this->optsIntoDonationReceipt;
	}

	public function getOptsIntoNewsletter(): bool {
		return $this->optsInToNewsletter;
	}

	public function setOptsIntoNewsletter( bool $optIn ): void {
		$this->optsInToNewsletter = $optIn;
	}

	public function getPaymentParameters(): PaymentParameters {
		return $this->paymentParameters ?? $this->paymentParameterBuilder->build();
	}

	public function setPaymentParameters( PaymentParameters $paymentParameters ): void {
		$this->paymentParameters = $paymentParameters;
	}
}
