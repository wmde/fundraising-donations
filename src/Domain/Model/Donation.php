<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

use DateTimeImmutable;
use RuntimeException;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\ScrubbedDonor;

class Donation {

	/**
	 * @var ModerationReason[]
	 */
	private array $moderationReasons;
	private bool $cancelled;

	private int $id;
	private Donor $donor;

	private int $paymentId;
	private ?DonationComment $comment;
	private DateTimeImmutable $donatedOn;
	private ?DateTimeImmutable $exportDate;

	/**
	 * TODO: move out of Donation when database model is refactored
	 * https://phabricator.wikimedia.org/T203679
	 *
	 * @var DonationTrackingInfo
	 */
	private DonationTrackingInfo $trackingInfo;

	/**
	 * @param int $id
	 * @param Donor $donor
	 * @param int $paymentId
	 * @param DonationTrackingInfo $trackingInfo
	 * @param DateTimeImmutable $donatedOn
	 * @param DonationComment|null $comment
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( int $id, Donor $donor, int $paymentId, DonationTrackingInfo $trackingInfo, DateTimeImmutable $donatedOn, DonationComment $comment = null ) {
		$this->id = $id;
		$this->donor = $donor;
		$this->paymentId = $paymentId;
		$this->trackingInfo = $trackingInfo;
		$this->donatedOn = $donatedOn;
		$this->comment = $comment;
		$this->exportDate = null;
		$this->cancelled = false;
		$this->moderationReasons = [];
	}

	public function getId(): int {
		return $this->id;
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
		return $this->donor->isSubscribedToMailingList();
	}

	public function cancel(): void {
		$this->cancelled = true;
	}

	public function revokeCancellation(): void {
		$this->cancelled = false;
	}

	public function confirmBooked(): void {
		if ( $this->isMarkedForModeration() || $this->isCancelled() ) {
			$this->makeCommentPrivate();
		}
	}

	private function makeCommentPrivate(): void {
		if ( $this->comment === null ) {
			return;
		}
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
		return $this->exportDate !== null;
	}

	public function markAsExported( DateTimeImmutable $exportDate = null ): void {
		$this->exportDate = $exportDate ?? new DateTimeImmutable();
	}

	public function getExportDate(): ?DateTimeImmutable {
		return $this->exportDate;
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
	 * See also https://phabricator.wiknimedia.org/T323710
	 *
	 * @deprecated
	 */
	public function getOptsIntoDonationReceipt(): bool {
		return $this->donor->wantsReceipt();
	}

	public function shouldSendConfirmationMail(): bool {
		if ( !$this->getDonor()->hasEmailAddress() ) {
			return false;
		}
		foreach ( $this->moderationReasons as $moderationReason ) {
			if ( $moderationReason->getModerationIdentifier() === ModerationIdentifier::EMAIL_BLOCKED ) {
				return false;
			}
		}
		return true;
	}

	public function donorIsAnonymous(): bool {
		return $this->donor instanceof AnonymousDonor || $this->donor instanceof ScrubbedDonor;
	}

	public function donorIsScrubbed(): bool {
		return $this->donor instanceof ScrubbedDonor;
	}

	public function createFollowupDonationForPayment( int $donationId, int $paymentId ): self {
		return new Donation(
			$donationId,
			$this->getDonor(),
			$paymentId,
			$this->getTrackingInfo(),
			// We don't want to clone comments for followup donations because they would show up again in the feed.
			// When we refactor the donation model,
			// we can point the comment to the comment of the original donation (db relationship)
			new DateTimeImmutable(),
			null
		);
	}

	public function getDonatedOn(): DateTimeImmutable {
		return $this->donatedOn;
	}

	public function scrubPersonalData( \DateTimeInterface $exportGracePeriodCutoffDate ): void {
		if ( !$this->scrubbingIsAllowed( $exportGracePeriodCutoffDate ) ) {
			throw new \DomainException( sprintf(
				"You must not anonymize unexported donations before %s, otherwise you'd lose data.",
				$exportGracePeriodCutoffDate->format( 'Y-m-d H:i:s' )
			) );
		}
		$this->donor = new ScrubbedDonor( $this->donor->getDonorType() );
	}

	/**
	 * We allow scrubbing of individual un-exported donations when the "grace period" (for completing payments) is over.
	 *
	 * Calling code is expected to calculate the grace period by subtracting an interval from the current time.
	 *
	 * Example: Scrubbing 2 donations with dates 2024-12-11 and 2024-12-13, on 2024-12-14 with a 2-day interval
	 *          for the grace period will lead to a cutoff date of 2024-12-12,
	 *          which allows for the donation on 2024-12-11 to be scrubbed
	 */
	private function scrubbingIsAllowed( \DateTimeInterface $exportGracePeriodCutoffDate ): bool {
		if ( $this->isExported() ) {
			return true;
		}
		if ( $this->donatedOn <= $exportGracePeriodCutoffDate ) {
			return true;
		}
		return false;
	}
}
