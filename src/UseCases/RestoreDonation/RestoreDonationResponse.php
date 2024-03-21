<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\RestoreDonation;

class RestoreDonationResponse {

	public const SUCCESS = 'success';
	public const FAILURE = 'failure';

	private int $donationId;
	private string $state;

	public function __construct( int $donationId, string $state ) {
		$this->donationId = $donationId;
		$this->state = $state;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function restoreSucceeded(): bool {
		return $this->state !== self::FAILURE;
	}
}
