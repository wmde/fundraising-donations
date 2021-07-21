<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;

/**
 * @license GPL-2.0-or-later
 */
class AddDonationRequest {

	private DonorType $donorType;
	private string $donorFirstName = '';
	private string $donorLastName = '';
	private string $donorSalutation = '';
	private string $donorTitle = '';
	private string $donorCompany = '';
	private string $donorStreetAddress = '';
	private string $donorPostalCode = '';
	private string $donorCity = '';
	private string $donorCountryCode = '';
	private string $donorEmailAddress = '';

	/**
	 * Newsletter subscription
	 *
	 * @var string
	 */
	private string $optIn = '';

	private Euro $amount;
	private string $paymentType = '';
	private int $interval = 0;

	private ?BankData $bankData;

	private string $tracking = '';
	private int $totalImpressionCount = 0;
	private int $singleBannerImpressionCount = 0;

	/**
	 * @var string
	 * @deprecated
	 */
	private string $color = '';

	/**
	 * @var string
	 * @deprecated
	 */
	private string $skin = '';

	/**
	 * @var string
	 * @deprecated
	 */
	private string $layout = '';

	private bool $optsIntoDonationReceipt = true;

	/**
	 * AddDonationRequest constructor.
	 */
	public function __construct() {
		$this->amount = Euro::newFromCents( 0 );
		$this->donorType = DonorType::ANONYMOUS();
	}

	public function getOptIn(): string {
		return $this->optIn;
	}

	public function setOptIn( string $optIn ): void {
		$this->optIn = trim( $optIn );
	}

	public function getAmount(): Euro {
		return $this->amount;
	}

	public function setAmount( Euro $amount ): void {
		$this->amount = $amount;
	}

	public function getPaymentType(): string {
		return $this->paymentType;
	}

	public function setPaymentType( string $paymentType ): void {
		$this->paymentType = trim( $paymentType );
	}

	public function getInterval(): int {
		return $this->interval;
	}

	public function setInterval( int $interval ): void {
		$this->interval = $interval;
	}

	public function getBankData(): ?BankData {
		return $this->bankData;
	}

	public function setBankData( BankData $bankData ): void {
		$this->bankData = $bankData;
	}

	public function getTracking(): string {
		return $this->tracking;
	}

	public function setTracking( string $tracking ): void {
		$this->tracking = trim( $tracking );
	}

	public function getTotalImpressionCount(): int {
		return $this->totalImpressionCount;
	}

	public function setTotalImpressionCount( int $totalImpressionCount ): void {
		$this->totalImpressionCount = $totalImpressionCount;
	}

	public function getSingleBannerImpressionCount(): int {
		return $this->singleBannerImpressionCount;
	}

	public function setSingleBannerImpressionCount( int $singleBannerImpressionCount ): void {
		$this->singleBannerImpressionCount = $singleBannerImpressionCount;
	}

	/**
	 * @deprecated
	 */
	public function getColor(): string {
		return $this->color;
	}

	/**
	 * @deprecated
	 */
	public function getSkin(): string {
		return $this->skin;
	}

	/**
	 * @deprecated
	 */
	public function getLayout(): string {
		return $this->layout;
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

	public function getDonorStreetAddress(): string {
		return $this->donorStreetAddress;
	}

	public function setDonorStreetAddress( string $donorStreetAddress ): void {
		$this->donorStreetAddress = trim( $donorStreetAddress );
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
		return $this->getDonorType()->is( DonorType::ANONYMOUS() );
	}

	public function setOptsIntoDonationReceipt( bool $optIn ): void {
		$this->optsIntoDonationReceipt = $optIn;
	}

	public function getOptsIntoDonationReceipt(): bool {
		return $this->optsIntoDonationReceipt;
	}

}
