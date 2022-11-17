<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

use RuntimeException;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;

/**
 * @license GPL-2.0-or-later
 */
class Donation {

	/**
	 * @var array ModerationReason[]
	 */
	private array $moderationReasons;
	private bool $cancelled;

	private ?int $id;
	private Donor $donor;

	private int $paymentId;
	private ?DonationComment $comment;
	private bool $exported;

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
	 * @param DonationTrackingInfo $trackingInfo
	 * @param DonationComment|null $comment
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( ?int $id, Donor $donor, int $paymentId, DonationTrackingInfo $trackingInfo, DonationComment $comment = null ) {
		$this->id = $id;
		$this->donor = $donor;
		$this->paymentId = $paymentId;
		$this->trackingInfo = $trackingInfo;
		$this->comment = $comment;
		$this->exported = false;
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

	public function getPaymentId(): int {
		return $this->paymentId;
	}

	/**
	 * This might be used by the fundraising application for display purposes,
	 * but should be removed when not used any more.
	 *
	 * See also https://phabricator.wikimedia.org/T323710
	 *
	 * @deprecated use $this->getDonor()->wantsNewsletter()
	 */
	public function getOptsIntoNewsletter(): bool {
		return $this->donor->wantsNewsletter();
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

	public function isMarkedForModeration(): bool {
		return count( $this->moderationReasons ) > 0;
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

	/**
	 * This might be used by the fundraising application for display purposes,
	 * but should be removed when not used any more.
	 *
	 * See also https://phabricator.wikimedia.org/T323710
	 *
	 * @deprecated
	 */
	public function getOptsIntoDonationReceipt(): bool {
		return $this->donor->wantsReceipt();
	}

	public function donorIsAnonymous(): bool {
		return $this->donor instanceof AnonymousDonor;
	}

	public function createFollowupDonationForPayment( int $paymentId ): self {
		return new Donation(
			null,
			$this->getDonor(),
			$paymentId,
			$this->getTrackingInfo(),
			// We don't want to clone comments for followup donations because they would show up again in the feed.
			// When we refactor the donation model,
			// we can point the comment to the comment of the original donation (db relationship)
			null
		);
	}
}
