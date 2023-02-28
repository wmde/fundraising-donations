<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

class CancelDonationResponse {

	public const SUCCESS = 'success';
	public const FAILURE = 'failure';
	public const MAIL_DELIVERY_FAILED = 'mail-not-send';

	private int $donationId;
	private string $state;

	public function __construct( int $donationId, string $state ) {
		$this->donationId = $donationId;
		$this->state = $state;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function cancellationSucceeded(): bool {
		return $this->state !== self::FAILURE;
	}

	public function mailDeliveryFailed(): bool {
		return $this->state === self::MAIL_DELIVERY_FAILED;
	}

}
