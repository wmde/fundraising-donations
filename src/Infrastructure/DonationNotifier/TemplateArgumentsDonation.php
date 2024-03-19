<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier;

class TemplateArgumentsDonation {
	/**
	 * @param int $id
	 * @param float $amount
	 * @param int $amountInCents
	 * @param int $interval
	 * @param string $paymentType
	 * @param bool $needsModeration
	 * @param array<string,boolean> $moderationFlags Name and state of {@see \WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier}
	 * @param string $bankTransferCode
	 * @param bool $receiptOptIn
	 */
	public function __construct(
		public readonly int $id,
		public readonly float $amount,
		public readonly int $amountInCents,
		public readonly int $interval,
		public readonly string $paymentType,
		public readonly bool $needsModeration,
		public readonly array $moderationFlags,
		public readonly string $bankTransferCode,
		public readonly bool $receiptOptIn,
	) {
	}
}
