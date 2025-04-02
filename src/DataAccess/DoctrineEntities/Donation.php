<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use WMDE\Fundraising\DonationContext\DataAccess\DonationData;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;

/**
 * This class represents the donation data in the database.
 *
 * As long as the database is not fully normalized, we still have many deprecated properties in here,
 * which we use in the Fundraising Operation center queries. You can remove the deprecated properties when
 * the Fundraising Operation Center does not query the columns any more.
 *
 * See https://phabricator.wikimedia.org/T203679 (database refactoring ticket)
 */
class Donation {

	// direct debit
	public const STATUS_NEW = 'N';

	// bank transfer
	public const STATUS_PROMISE = 'Z';

	// external payment, not notified by payment provider
	public const STATUS_EXTERNAL_INCOMPLETE = 'X';

	// external payment, notified by payment provider
	public const STATUS_EXTERNAL_BOOKED = 'B';

	/**
	 * Status for donation that are marked as moderated
	 *
	 * @deprecated Check count of moderationReasons. If > 0, donation is moderated
	 */
	public const STATUS_MODERATION = 'P';

	/**
	 * Status for soft-deletion (canceled by the user in the UI or "deleted" by an administrator)
	 */
	public const STATUS_CANCELLED = 'D';

	/**
	 * @deprecated This status is defined for historical reasons. It should not be used to define a
	 * donation's status any more. To mark donations as exported, set dtGruen instead.
	 */
	public const STATUS_EXPORTED = 'E';

	private ?int $id = null;

	private string $status = self::STATUS_NEW;

	private ?string $donorFullName = null;

	private ?string $donorCity = null;

	private ?string $donorEmail = null;

	private bool $donorOptsIntoNewsletter = false;

	private ?bool $donationReceipt = null;

	private string $publicRecord = '';

	/**
	 * @deprecated Remove when Fundraising Operation Center does not query this any more
	 *
	 * @var string|null
	 */
	private ?string $amount = null;

	/**
	 * @deprecated Remove when Fundraising Operation Center does not query this any more
	 *
	 * @var int
	 */
	private int $paymentIntervalInMonths = 0;

	/**
	 * @deprecated Remove when Fundraising Operation Center does not query this any more
	 *
	 * @var string
	 */
	private string $paymentType = 'BEZ';

	private string $comment = '';

	private string $bankTransferCode = '';

	private ?string $data = null;

	private ?string $source = null;

	private string $remoteAddr = '';

	private string $hash;

	private bool $isPublic = false;

	/**
	 * This represents the fact that the personal data was removed from the donation.
	 * This is different from addressType = 'anonym' which indicates that the user donated anonymously.
	 */
	private bool $isScrubbed = false;

	private \DateTime $creationTime;

	private ?\DateTime $deletionTime = null;

	/**
	 * Legacy "Exported" date
	 *
	 * @deprecated Use {@see dtGruen} instead
	 *
	 * @var \DateTime|null
	 */
	private ?\DateTime $dtExp = null;

	/**
	 * Export date, null means unexported donation
	 *
	 * @var \DateTime|null
	 */
	private ?\DateTime $dtGruen = null;

	private ?\DateTime $dtBackup = null;

	/**
	 * @var Collection<int|string,ModerationReason>
	 */
	private Collection $moderationReasons;

	private int $paymentId;

	private DonationTracking $donationTracking;

	private int $impressionCount = 0;

	private int $bannerImpressionCount = 0;

	public function __construct() {
		$this->moderationReasons = new ArrayCollection( [] );
		$this->creationTime = new \DateTime();
	}

	public function setDonorFullName( string $donorFullName ): self {
		$this->donorFullName = $donorFullName;

		return $this;
	}

	public function getDonorFullName(): ?string {
		return $this->donorFullName;
	}

	public function setDonorCity( string $donorCity ): self {
		$this->donorCity = $donorCity;

		return $this;
	}

	public function getDonorCity(): ?string {
		return $this->donorCity;
	}

	public function setDonorEmail( string $donorEmail ): self {
		$this->donorEmail = $donorEmail;

		return $this;
	}

	public function getDonorEmail(): ?string {
		return $this->donorEmail;
	}

	public function setDonorOptsIntoNewsletter( bool $donorOptsIntoNewsletter ): self {
		$this->donorOptsIntoNewsletter = $donorOptsIntoNewsletter;

		return $this;
	}

	public function getDonorOptsIntoNewsletter(): bool {
		return $this->donorOptsIntoNewsletter;
	}

	/**
	 * Set donation receipt state
	 *
	 * @param bool $donationReceipt
	 * @return self
	 */
	public function setDonationReceipt( ?bool $donationReceipt ): self {
		$this->donationReceipt = $donationReceipt;

		return $this;
	}

	/**
	 * Get donation receipt state
	 *
	 * This field was introduced without proper migration, so it can be null
	 *
	 * @return bool|null
	 */
	public function getDonationReceipt(): ?bool {
		return $this->donationReceipt;
	}

	/**
	 * Set publicly displayed donation record
	 *
	 * @param string $publicRecord
	 * @return self
	 */
	public function setPublicRecord( string $publicRecord ): self {
		$this->publicRecord = $publicRecord;

		return $this;
	}

	/**
	 * Get publicly displayed donation record
	 *
	 * @return string
	 */
	public function getPublicRecord(): string {
		return $this->publicRecord;
	}

	/**
	 * @deprecated Set payment ID instead
	 *
	 * @param string $amount
	 * @return $this
	 */
	public function setAmount( string $amount ): self {
		$this->amount = $amount;

		return $this;
	}

	/**
	 * @deprecated Use payment ID to join payments table or to get payment entity
	 *
	 * @return string
	 */
	public function getAmount(): string {
		return $this->amount ?? '0';
	}

	/**
	 * @deprecated Set payment ID instead
	 *
	 * @param int $paymentIntervalInMonths
	 * @return $this
	 */
	public function setPaymentIntervalInMonths( int $paymentIntervalInMonths ): self {
		$this->paymentIntervalInMonths = $paymentIntervalInMonths;

		return $this;
	}

	/**
	 * @deprecated Use payment ID to join payments table or to get payment entity
	 *
	 * @return int
	 */
	public function getPaymentIntervalInMonths(): int {
		return $this->paymentIntervalInMonths;
	}

	/**
	 * Set payment type short code
	 *
	 * @deprecated Set payment ID instead
	 *
	 * @param string $paymentType
	 * @return self
	 */
	public function setPaymentType( string $paymentType ): self {
		$this->paymentType = $paymentType;

		return $this;
	}

	/**
	 * Get payment type short code
	 *
	 * @deprecated Use payment ID to join payments table or to get payment entity
	 *
	 * @return string
	 */
	public function getPaymentType(): string {
		return $this->paymentType;
	}

	public function setComment( string $comment ): self {
		$this->comment = $comment;

		return $this;
	}

	public function getComment(): string {
		return $this->comment;
	}

	/**
	 * Set bank transfer reference code
	 *
	 * @param string $bankTransferCode
	 *
	 * @return self
	 */
	public function setBankTransferCode( string $bankTransferCode ): self {
		$this->bankTransferCode = $bankTransferCode;

		return $this;
	}

	/**
	 * Get bank transfer reference code
	 *
	 * @return string
	 */
	public function getBankTransferCode(): string {
		return $this->bankTransferCode;
	}

	public function setSource( ?string $source ): self {
		$this->source = $source;

		return $this;
	}

	public function getSource(): ?string {
		return $this->source;
	}

	public function setRemoteAddr( string $remoteAddr ): self {
		$this->remoteAddr = $remoteAddr;

		return $this;
	}

	public function getRemoteAddr(): string {
		return $this->remoteAddr;
	}

	public function setHash( string $hash ): self {
		$this->hash = $hash;

		return $this;
	}

	public function getHash(): string {
		return $this->hash;
	}

	/**
	 * Sets if the donations comment should be public or private.
	 * @param bool $isPublic
	 * @return self
	 */
	public function setIsPublic( bool $isPublic ): self {
		$this->isPublic = $isPublic;

		return $this;
	}

	/**
	 * Gets if the donations comment is public or private.
	 * @return bool
	 */
	public function getIsPublic(): bool {
		return $this->isPublic;
	}

	public function setCreationTime( \DateTime $creationTime ): self {
		$this->creationTime = $creationTime;

		return $this;
	}

	public function getCreationTime(): \DateTime {
		return $this->creationTime;
	}

	public function setDeletionTime( ?\DateTime $deletionTime ): self {
		$this->deletionTime = $deletionTime;

		return $this;
	}

	public function getDeletionTime(): ?\DateTime {
		return $this->deletionTime;
	}

	public function setDtExp( ?\DateTime $dtExp ): self {
		$this->dtExp = $dtExp;

		return $this;
	}

	public function getDtExp(): ?\DateTime {
		return $this->dtExp;
	}

	public function setStatus( string $status ): self {
		$this->status = $status;

		return $this;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function setDtGruen( ?\DateTime $dtGruen ): self {
		$this->dtGruen = $dtGruen;

		return $this;
	}

	public function getDtGruen(): ?\DateTime {
		return $this->dtGruen;
	}

	public function setDtBackup( ?\DateTime $dtBackup ): self {
		$this->dtBackup = $dtBackup;

		return $this;
	}

	public function getDtBackup(): ?\DateTime {
		return $this->dtBackup;
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getPaymentId(): int {
		return $this->paymentId;
	}

	public function setPaymentId( int $paymentId ): void {
		$this->paymentId = $paymentId;
	}

	public function setId( ?int $id ): void {
		$this->id = $id;
	}

	/**
	 * NOTE: if possible, use @see getDataObject instead, as it provides a nicer API.
	 *
	 * @return array<string,mixed>
	 */
	public function getDecodedData(): array {
		if ( $this->data === null ) {
			return [];
		}

		$data = unserialize( base64_decode( $this->data ) );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * NOTE: if possible, use @see modifyDataObject instead, as it provides a nicer API.
	 *
	 * @param array<string,mixed> $data
	 */
	public function encodeAndSetData( array $data ): void {
		$this->data = base64_encode( serialize( $data ) );
	}

	/**
	 * WARNING: updates made to the return value will not be reflected in the Donation state.
	 * Similarly, updates to the Donation state will not propagate to the returned object.
	 * To update the Donation state, explicitly call @see setDataObject.
	 *
	 * @return DonationData
	 */
	public function getDataObject(): DonationData {
		$dataArray = $this->getDecodedData();

		$data = new DonationData();

		$data->setAccessToken( array_key_exists( 'token', $dataArray ) && is_string( $dataArray['token'] ) ? $dataArray['token'] : null );
		$data->setUpdateToken( array_key_exists( 'utoken', $dataArray ) && is_string( $dataArray['utoken'] ) ? $dataArray['utoken'] : null );
		$data->setUpdateTokenExpiry( array_key_exists( 'uexpiry', $dataArray ) && is_string( $dataArray['uexpiry'] ) ? $dataArray['uexpiry'] : null );

		return $data;
	}

	public function setDataObject( DonationData $data ): void {
		$dataArray = array_merge(
			$this->getDecodedData(),
			[
				'token' => $data->getAccessToken(),
				'utoken' => $data->getUpdateToken(),
				'uexpiry' => $data->getUpdateTokenExpiry(),
			]
		);

		foreach ( [ 'token', 'utoken', 'uexpiry' ] as $keyName ) {
			if ( $dataArray[$keyName] === null ) {
				unset( $dataArray[$keyName] );
			}
		}

		$this->encodeAndSetData( $dataArray );
	}

	/**
	 * @param callable $modificationFunction Takes a modifiable DonationData parameter
	 */
	public function modifyDataObject( callable $modificationFunction ): void {
		$dataObject = $this->getDataObject();
		$modificationFunction( $dataObject );
		$this->setDataObject( $dataObject );
	}

	public function setModerationReasons( ModerationReason ...$moderationReasons ): self {
		$this->moderationReasons = new ArrayCollection( $moderationReasons );
		return $this;
	}

	/**
	 * @return Collection<int|string,ModerationReason>
	 */
	public function getModerationReasons(): Collection {
		return $this->moderationReasons;
	}

	public function isScrubbed(): bool {
		return $this->isScrubbed;
	}

	public function scrub(): void {
		$this->isScrubbed = true;
	}

	public function getDonationTracking(): DonationTracking {
		return $this->donationTracking;
	}

	public function setDonationTracking( DonationTracking $donationTracking ): void {
		$this->donationTracking = $donationTracking;
	}

	public function getImpressionCount(): int {
		return $this->impressionCount;
	}

	public function setImpressionCount( int $impressionCount ): void {
		$this->impressionCount = $impressionCount;
	}

	public function getBannerImpressionCount(): int {
		return $this->bannerImpressionCount;
	}

	public function setBannerImpressionCount( int $bannerImpressionCount ): void {
		$this->bannerImpressionCount = $bannerImpressionCount;
	}
}
