<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use WMDE\Fundraising\DonationContext\DataAccess\DonationData;

/**
 * @ORM\Table(
 *     name="spenden",
 *     indexes={
 *         @ORM\Index(name="d_email", columns={"email"}, flags={"fulltext"}),
 *         @ORM\Index(name="d_name", columns={"name"}, flags={"fulltext"}),
 *         @ORM\Index(name="d_ort", columns={"ort"}, flags={"fulltext"}),
 *         @ORM\Index(name="d_dt_new", columns={"dt_new", "is_public"}),
 *         @ORM\Index(name="d_zahlweise", columns={"zahlweise", "dt_new"}),
 *         @ORM\Index(name="d_dt_gruen", columns={"dt_gruen", "dt_del"}),
 *         @ORM\Index(name="d_ueb_code", columns={"ueb_code"}),
 *         @ORM\Index(name="d_dt_backup", columns={"dt_backup"}),
 *         @ORM\Index(name="d_status", columns={"status", "dt_new"}),
 *         @ORM\Index(name="d_comment_list", columns={"is_public", "dt_del"})
 *     }
 * )
 * @ORM\Entity
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
	public const STATUS_MODERATION = 'P';
	public const STATUS_CANCELLED = 'D';

	/**
	 * @deprecated since 6.1; This status is defined for historical reasons. It should not be used to define a
	 * donation's status anymore.
	 */
	public const STATUS_EXPORTED = 'E';

	/**
	 * @var int
	 *
	 * @ORM\Column(name="id", type="integer")
	 * @ORM\Id
	 * @ORM\GeneratedValue(strategy="IDENTITY")
	 */
	private $id;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="status", type="string", length=1, options={"default":"N", "fixed":true}, nullable=false)
	 */
	private $status = self::STATUS_NEW;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="name", type="string", length=250, nullable=true)
	 */
	private $donorFullName;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="ort", type="string", length=250, nullable=true)
	 */
	private $donorCity;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="email", type="string", length=250, nullable=true)
	 */
	private $donorEmail;

	/**
	 * @var bool
	 *
	 * @ORM\Column(name="info", type="boolean", options={"default":0}, nullable=false)
	 */
	private $donorOptsIntoNewsletter = false;

	/**
	 * @var bool
	 *
	 * @ORM\Column(name="bescheinigung", type="boolean", nullable=true)
	 */
	private $donationReceipt;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="eintrag", type="string", length=250, options={"default":""}, nullable=false)
	 */
	private $publicRecord = '';

	/**
	 * @var string
	 *
	 * @ORM\Column(name="betrag", type="string", length=250, nullable=true)
	 */
	private $amount;

	/**
	 * @var int
	 *
	 * @ORM\Column(name="periode", type="smallint", options={"default":0}, nullable=false)
	 */
	private $paymentIntervalInMonths = 0;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="zahlweise", type="string", length=3, options={"default":"BEZ", "fixed":true}, nullable=false)
	 */
	private $paymentType = 'BEZ';

	/**
	 * @var string
	 *
	 * @ORM\Column(name="kommentar", type="text", options={"default":""}, nullable=false)
	 */
	private $comment = '';

	/**
	 * @var string
	 *
	 * @ORM\Column(name="ueb_code", type="string", length=32, options={"default":""}, nullable=false)
	 */
	private $bankTransferCode = '';

	/**
	 * @var string
	 *
	 * @ORM\Column(name="data", type="text", nullable=true)
	 */
	private $data;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="source", type="string", length=250, nullable=true)
	 */
	private $source;

	/**
	 * @var string
	 *
	 * @ORM\Column(name="remote_addr", type="string", length=250, options={"default":""}, nullable=false)
	 */
	private $remoteAddr = '';

	/**
	 * @var string
	 *
	 * @ORM\Column(name="hash", type="string", length=250, nullable=true)
	 */
	private $hash;

	/**
	 * @var bool
	 *
	 * @ORM\Column(name="is_public", type="boolean", options={"default":0}, nullable=false)
	 */
	private $isPublic = false;

	/**
	 * @var \DateTime
	 *
	 * @Gedmo\Timestampable(on="create")
	 * @ORM\Column(name="dt_new", type="datetime")
	 */
	private $creationTime;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="dt_del", type="datetime", nullable=true)
	 */
	private $deletionTime;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="dt_exp", type="datetime", nullable=true)
	 */
	private $dtExp;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="dt_gruen", type="datetime", nullable=true)
	 */
	private $dtGruen;

	/**
	 * @var \DateTime
	 *
	 * @ORM\Column(name="dt_backup", type="datetime", nullable=true)
	 */
	private $dtBackup;

	/**
	 * @var DonationPayment
	 *
	 * @ORM\OneToOne(targetEntity="WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayment", cascade={"all"}, fetch="EAGER")
	 */
	private $payment;

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

	public function getDonorCity(): string {
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
	 * @return bool
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

	public function setAmount( string $amount ): self {
		$this->amount = $amount;

		return $this;
	}

	public function getAmount(): string {
		return $this->amount;
	}

	public function setPaymentIntervalInMonths( int $paymentIntervalInMonths ): self {
		$this->paymentIntervalInMonths = $paymentIntervalInMonths;

		return $this;
	}

	public function getPaymentIntervalInMonths(): int {
		return $this->paymentIntervalInMonths;
	}

	/**
	 * Set payment type short code
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

	public function getPayment(): ?DonationPayment {
		return $this->payment;
	}

	public function setPayment( DonationPayment $payment ) {
		$this->payment = $payment;
	}

	public function setId( ?int $id ) {
		$this->id = $id;
	}

	/**
	 * Get name for donations comments
	 *
	 * @deprecated This functionality should be moved to the domain/presentation code.
	 */
	public function getEntryType( ?int $mode = null ): string {
		$data = $this->getDecodedData();

		if ( $mode === null ) {
			$mode = $this->publicRecord;
			if ( !is_int( $mode ) ) {
				return $this->publicRecord;
			}
		}

		if ( $mode == 1 || $mode == 2 ) {
			$eintrag = $this->donorFullName;
		} else {
			$eintrag = 'anonym';
		}

		if ( ( $mode == 1 || $mode == 3 ) && !empty( $data['ort'] ) ) {
			$eintrag .= ', ' . $data['ort'];
		}

		return $eintrag;
	}

	/**
	 * @deprecated Use @see getDecodedData or @see getDataObject instead
	 *
	 * @param string $data Base 64 encoded, serialized PHP array
	 * @return self
	 */
	public function setData( string $data ): self {
		$this->data = $data;

		return $this;
	}

	/**
	 * @deprecated @see getDecodedData or @see getDataObject instead
	 *
	 * @return string Base 64 encoded, serialized PHP array
	 */
	public function getData(): string {
		return $this->data;
	}

	/**
	 * NOTE: if possible, use @see getDataObject instead, as it provides a nicer API.
	 *
	 * @return array
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
	 * @param array $data
	 */
	public function encodeAndSetData( array $data ) {
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

		$data->setAccessToken( array_key_exists( 'token', $dataArray ) ? $dataArray['token'] : null );
		$data->setUpdateToken( array_key_exists( 'utoken', $dataArray ) ? $dataArray['utoken'] : null );
		$data->setUpdateTokenExpiry( array_key_exists( 'uexpiry', $dataArray ) ? $dataArray['uexpiry'] : null );

		return $data;
	}

	public function setDataObject( DonationData $data ) {
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
	public function modifyDataObject( callable $modificationFunction ) {
		$dataObject = $this->getDataObject();
		$modificationFunction( $dataObject );
		$this->setDataObject( $dataObject );
	}
}
