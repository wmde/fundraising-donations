<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

use RuntimeException;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\DummyPayment;
use WMDE\Fundraising\DonationContext\RefactoringException;
use WMDE\Fundraising\PaymentContext\Domain\Model\BookablePayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

/**
 * @license GPL-2.0-or-later
 */
class Donation {

	/**
	 * @var array ModerationReason[]
	 */
	private array $moderationReasons;
	private bool $cancelled;

	public const OPTS_INTO_NEWSLETTER = true;
	public const DOES_NOT_OPT_INTO_NEWSLETTER = false;

	private ?int $id;
	private Donor $donor;

	private int $paymentId;
	private bool $optsIntoNewsletter;
	private ?DonationComment $comment;
	private bool $exported;

	/**
	 * If the user wants to receive a donation receipt
	 *
	 * Can be null as there are historic, and machine-made records without this information
	 *
	 * @var bool|null
	 */
	private ?bool $optsIntoDonationReceipt;

	/**
	 * TODO: move out of Donation when database model is refactored
	 * https://phabricator.wikimedia.org/T203679
	 *
	 * @var DonationTrackingInfo
	 */
	private DonationTrackingInfo $trackingInfo;

	/**
	 * @param int|null $id
	 * @param Donor $donor
	 * @param int $paymentId
	 * @param bool $optsIntoNewsletter
	 * @param DonationTrackingInfo $trackingInfo
	 * @param DonationComment|null $comment
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( ?int $id, Donor $donor, int $paymentId,
								bool $optsIntoNewsletter, DonationTrackingInfo $trackingInfo, DonationComment $comment = null ) {
		$this->id = $id;
		$this->donor = $donor;
		$this->paymentId = $paymentId;
		$this->optsIntoNewsletter = $optsIntoNewsletter;
		$this->trackingInfo = $trackingInfo;
		$this->comment = $comment;
		$this->exported = false;
		$this->optsIntoDonationReceipt = null;
		$this->cancelled = false;
		$this->moderationReasons = [];
	}

	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * @param int $id
	 *
	 * @throws \RuntimeException
	 */
	public function assignId( int $id ): void {
		if ( $this->id !== null && $this->id !== $id ) {
			throw new \RuntimeException( 'Id cannot be changed after initial assignment' );
		}

		$this->id = $id;
	}

	/**
	 * @deprecated Use a payment instead
	 * @return Euro
	 */
	public function getAmount(): Euro {
		return Euro::newFromCents( 0 );
	}

	/**
	 * @deprecated
	 *
	 * @return int
	 */
	public function getPaymentIntervalInMonths(): int {
		throw new RefactoringException( 'You shall not ask donations for payment intervals!' );
	}

	/**
	 * @deprecated
	 *
	 * @return string
	 */
	public function getPaymentMethodId(): string {
		throw new RefactoringException( 'You shall not ask donations for payment method IDs!' );
	}

	public function getDonor(): Donor {
		return $this->donor;
	}

	public function setDonor( Donor $donor ): void {
		$this->donor = $donor;
	}

	public function getComment(): ?DonationComment {
		return $this->comment;
	}

	public function addComment( DonationComment $comment ): void {
		if ( $this->hasComment() ) {
			throw new RuntimeException( 'Can only add a single comment to a donation' );
		}

		$this->comment = $comment;
	}

	/**
	 * @deprecated Donation code should not interact with the payment entity,
	 *      this is here for BC compatibility and should be gone at the end of the payment integration refactoring
	 *
	 * @return Payment
	 */
	public function getPayment(): Payment {
		return DummyPayment::create();
	}

	public function getPaymentId(): int {
		return $this->paymentId;
	}

	public function getPaymentMethod(): string {
		throw new RefactoringException( 'You shall not ask donations for payment methods!' );
	}

	public function getOptsIntoNewsletter(): bool {
		return $this->optsIntoNewsletter;
	}

	public function cancel(): void {
		$this->cancelled = true;
	}

	public function revokeCancellation(): void {
		$this->cancelled = false;
	}

	public function confirmBooked(): void {
		if ( $this->hasComment() && ( $this->isMarkedForModeration() || $this->isCancelled() ) ) {
			$this->makeCommentPrivate();
		}
	}

	private function makeCommentPrivate(): void {
		$this->comment = new DonationComment(
			$this->comment->getCommentText(),
			false,
			$this->comment->getAuthorDisplayName()
		);
	}

	public function hasComment(): bool {
		return $this->comment !== null;
	}

	/**
	 * @param ModerationReason ...$moderationReasons provide at least 1 ModerationReason to mark for moderation
	 */
	public function markForModeration( ModerationReason ...$moderationReasons ): void {
		if ( empty( $moderationReasons ) ) {
			throw new \LogicException( "you must provide at least one ModerationReason to mark a donation for moderation" );
		}
		$this->moderationReasons = array_merge( $this->moderationReasons, $moderationReasons );
	}

	public function approve(): void {
		$this->moderationReasons = [];
	}

	/**
	 * @return ModerationReason[]
	 */
	public function getModerationReasons(): array {
		return $this->moderationReasons;
	}

	public function getTrackingInfo(): DonationTrackingInfo {
		return $this->trackingInfo;
	}

	/**
	 * @deprecated The donation should not know anything about the payment.
	 *
	 * @return bool
	 */
	public function hasBookablePayment(): bool {
		return $this->getPayment() instanceof BookablePayment;
	}

	public function isMarkedForModeration(): bool {
		return count( $this->moderationReasons ) > 0;
	}

	public function isBooked(): bool {
		throw new RefactoringException( 'You shall not ask donations for payment booking state! Or at least check if this method is really needed' );
	}

	public function isExported(): bool {
		return $this->exported;
	}

	public function markAsExported(): void {
		$this->exported = true;
	}

	public function isCancelled(): bool {
		return $this->cancelled;
	}

	/**
	 * Code that reacts to user actions should use cancel()
	 * This method is only for automated internal processes (deleting after failing policy checks,...)
	 */
	public function cancelWithoutChecks(): void {
		$this->cancelled = true;
	}

	public function setOptsIntoDonationReceipt( ?bool $optOut ): void {
		$this->optsIntoDonationReceipt = $optOut;
	}

	public function getOptsIntoDonationReceipt(): ?bool {
		return $this->optsIntoDonationReceipt;
	}

	public function donorIsAnonymous(): bool {
		return $this->donor instanceof AnonymousDonor;
	}
}
