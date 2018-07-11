<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;

/**
 * @license GNU GPL v2+
 */
class UpdateDonorResponse {

	public const SUCCESS_TEXT = 'donor_change_success_text';
	public const VIOLATION_GENERIC = 'donor_change_failure_generic';

	private $donation;

	public static function newSuccessResponse( string $successMessage, Donation $donation = null ): self {
		return new self( '', $successMessage, $donation );
	}

	public static function newFailureResponse( string $errorMessage ): self {
		return new self( $errorMessage, '' );
	}

	private $errorMessage;
	private $successMessage;

	private function __construct( string $errorMessage = null, string $successMessage = null, Donation $donation = null ) {
		$this->errorMessage = $errorMessage;
		$this->successMessage = $successMessage;
		$this->donation = $donation;
	}

	public function isSuccessful(): bool {
		return $this->errorMessage === '';
	}

	public function getDonation(): ?Donation {
		return $this->donation;
	}

	/**
	 * Returns the error message, or empty string in case the request was a success.
	 *
	 * @return string
	 */
	public function getErrorMessage(): string {
		return $this->errorMessage;
	}

	public function getSuccessMessage(): string {
		return $this->successMessage;
	}
}
