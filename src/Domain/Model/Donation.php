<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

use RuntimeException;
use WMDE\Euro\Euro;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardTransactionData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

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
	public const STATUS_MODERATION = 'P';
	public const STATUS_CANCELLED = 'D';

	public const OPTS_INTO_NEWSLETTER = true;
	public const DOES_NOT_OPT_INTO_NEWSLETTER = false;

	private ?int $id;
	private string $status;
	private Donor $donor;
	private DonationPayment $payment;
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
	 */
	private DonationTrackingInfo $trackingInfo;

	/**
	 * @param int|null $id
	 * @param string $status Must be one of the Donation::STATUS_ constants
	 * @param Donor $donor
	 * @param DonationPayment $payment
	 * @param bool $optsIntoNewsletter
	 * @param DonationTrackingInfo $trackingInfo
	 * @param DonationComment|null $comment
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct( ?int $id, string $status, Donor $donor, DonationPayment $payment,
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
	}

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
				self::STATUS_MODERATION,
				self::STATUS_CANCELLED,
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
	 */
	public function getStatus(): string {
		return $this->status;
	}

	public function getAmount(): Euro {
		return $this->payment->getAmount();
	}

	public function getPaymentIntervalInMonths(): int {
		return $this->payment->getIntervalInMonths();
	}

	public function getPaymentMethodId(): string {
		return $this->getPaymentMethod()->getId();
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

	public function getPayment(): DonationPayment {
		return $this->payment;
	}

	public function getPaymentMethod(): PaymentMethod {
		return $this->payment->getPaymentMethod();
	}

	public function getOptsIntoNewsletter(): bool {
		return $this->optsIntoNewsletter;
	}

	public function cancel(): void {
		if ( $this->getPaymentMethodId() !== PaymentMethod::DIRECT_DEBIT ) {
			throw new RuntimeException( 'Can only cancel direct debit' );
		}

		if ( !$this->statusIsCancellable() ) {
			throw new RuntimeException( 'Can only cancel new donations' );
		}

		$this->status = self::STATUS_CANCELLED;
	}

	/**
	 * @throws RuntimeException
	 */
	public function confirmBooked(): void {
		if ( !$this->hasExternalPayment() ) {
			throw new RuntimeException( 'Only external payments can be confirmed as booked' );
		}

		if ( !$this->statusAllowsForBooking() ) {
			throw new RuntimeException( 'Only incomplete donations can be confirmed as booked' );
		}

		if ( $this->hasComment() && ( $this->needsModeration() || $this->isCancelled() ) ) {
			$this->makeCommentPrivate();
		}

		$this->status = self::STATUS_EXTERNAL_BOOKED;
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

	private function statusAllowsForBooking(): bool {
		return $this->isIncomplete() || $this->needsModeration() || $this->isCancelled();
	}

	public function markForModeration(): void {
		$this->status = self::STATUS_MODERATION;
	}

	public function notifyOfPolicyValidationFailure(): void {
		if ( !$this->hasExternalPayment() ) {
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
	 * @param CreditCardTransactionData $creditCardData
	 *
	 * @throws RuntimeException
	 */
	public function addCreditCardData( CreditCardTransactionData $creditCardData ): void {
		$paymentMethod = $this->payment->getPaymentMethod();

		if ( !( $paymentMethod instanceof CreditCardPayment ) ) {
			throw new RuntimeException( 'Cannot set credit card transaction data on a non credit card payment' );
		}

		$paymentMethod->addCreditCardTransactionData( $creditCardData );
	}

	private function statusIsCancellable(): bool {
		return $this->status === self::STATUS_NEW || $this->status === self::STATUS_MODERATION;
	}

	public function hasExternalPayment(): bool {
		return in_array(
			$this->getPaymentMethodId(),
			[ PaymentMethod::PAYPAL, PaymentMethod::CREDIT_CARD, PaymentMethod::SOFORT ]
		);
	}

	private function isIncomplete(): bool {
		return $this->status === self::STATUS_EXTERNAL_INCOMPLETE;
	}

	public function needsModeration(): bool {
		return $this->status === self::STATUS_MODERATION;
	}

	public function isBooked(): bool {
		return $this->status === self::STATUS_EXTERNAL_BOOKED;
	}

	public function isExported(): bool {
		return $this->exported;
	}

	public function markAsExported(): void {
		$this->exported = true;
	}

	public function isCancelled(): bool {
		return $this->status === self::STATUS_CANCELLED;
	}

	public function markAsDeleted(): void {
		$this->status = self::STATUS_CANCELLED;
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
