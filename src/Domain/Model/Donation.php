<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

use RuntimeException;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\RefactoringException;
use WMDE\Fundraising\PaymentContext\Domain\Model\BookablePayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CancellablePayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;

/**
 * @license GPL-2.0-or-later
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

	private bool $moderationNeeded;
	private bool $cancelled;

	public const OPTS_INTO_NEWSLETTER = true;
	public const DOES_NOT_OPT_INTO_NEWSLETTER = false;

	private ?int $id;
	private string $status;
	private Donor $donor;
	/**
	 * @var Payment
	 * @deprecated Use payment ID instead
	 */
	private Payment $payment;
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
	 * @param string $status Must be one of the Donation::STATUS_ constants. Will be deprecated, see https://phabricator.wikimedia.org/T276817
	 * @param Donor $donor
	 * @param Payment $payment TODO Deprecated, Pass paymentId instead
	 * @param bool $optsIntoNewsletter
	 * @param DonationTrackingInfo $trackingInfo
	 * @param DonationComment|null $comment
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( ?int $id, string $status, Donor $donor, Payment $payment,
		bool $optsIntoNewsletter, DonationTrackingInfo $trackingInfo, DonationComment $comment = null ) {
		$this->id = $id;
		$this->setStatus( $status );
		$this->donor = $donor;
		$this->payment = $payment;
		$this->optsIntoNewsletter = $optsIntoNewsletter;
		$this->trackingInfo = $trackingInfo;
		$this->comment = $comment;
		$this->exported = false;
		$this->optsIntoDonationReceipt = null;
		$this->moderationNeeded = false;
		$this->cancelled = false;
		$this->paymentId = $payment->getId();
	}

	/**
	 * @param string $status
	 * @deprecated See https://phabricator.wikimedia.org/T276817
	 */
	private function setStatus( string $status ): void {
		if ( !$this->isValidStatus( $status ) ) {
			throw new \InvalidArgumentException( 'Invalid donation status' );
		}

		$this->status = $status;
	}

	private function isValidStatus( string $status ): bool {
		return in_array(
			$status,
			[
				self::STATUS_NEW,
				self::STATUS_PROMISE,
				self::STATUS_EXTERNAL_INCOMPLETE,
				self::STATUS_EXTERNAL_BOOKED,
			]
		);
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
	 * Usage of more specific methods such as isBooked or statusAllowsForCancellation is recommended.
	 *
	 * @return string One of the Donation::STATUS_ constants
	 * @deprecated See https://phabricator.wikimedia.org/T276817
	 */
	public function getStatus(): string {
		return $this->status;
	}

	public function getAmount(): Euro {
		return $this->payment->getAmount();
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
		return $this->payment;
	}

	public function getPaymentMethod(): string {
		throw new RefactoringException( 'You shall not ask donations for payment methods!' );
	}

	public function getOptsIntoNewsletter(): bool {
		return $this->optsIntoNewsletter;
	}

	public function cancel(): void {
		if ( !$this->isCancellable() ) {
			throw new RuntimeException( 'Can only cancel un-exported donations with non-external payment providers' );
		}
		$this->cancelled = true;
	}

	public function revokeCancellation(): void {
		$this->cancelled = false;
	}

	public function confirmBooked( array $paymentTransactionData ): void {
		// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable
		throw new RefactoringException( 'TODO: your use case should call the payment booking with transaction data, this method is just for followup data changes' );

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

	public function markForModeration(): void {
		$this->moderationNeeded = true;
	}

	public function approve(): void {
		$this->moderationNeeded = false;
	}

	public function notifyOfPolicyValidationFailure(): void {
		if ( !$this->hasBookablePayment() ) {
			$this->markForModeration();
		}
	}

	public function notifyOfCommentValidationFailure(): void {
		$this->markForModeration();
	}

	public function getTrackingInfo(): DonationTrackingInfo {
		return $this->trackingInfo;
	}

	/**
	 * @deprecated The donation should not know anything about the payment. Instead, try to cancel the payment, then cancel the donation.
	 *
	 * @return bool
	 */
	private function isCancellable(): bool {
		return ( $this->payment instanceof CancellablePayment ) && $this->payment->isCancellable();
	}

	/**
	 * @deprecated The donation should not know anything about the payment.
	 *
	 * @return bool
	 */
	public function hasBookablePayment(): bool {
		return $this->payment instanceof BookablePayment;
	}

	public function isMarkedForModeration(): bool {
		return $this->moderationNeeded;
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
