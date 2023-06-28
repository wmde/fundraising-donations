<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;

/**
 * @license GPL-2.0-or-later
 */
class UpdateDonorResponse {

	public const SUCCESS_TEXT = 'donor_change_success_text';
	public const ERROR_ACCESS_DENIED = 'donor_change_failure_access_denied';
	public const ERROR_DONATION_NOT_FOUND = 'donor_change_failure_not_found';
	public const ERROR_DONATION_IS_EXPORTED = 'donor_change_failure_exported';
	public const ERROR_VALIDATION_FAILED = 'donor_change_failure_validation_error';

	private ?Donation $donation;
	private string $errorMessage;
	private string $successMessage;

	public static function newSuccessResponse( string $successMessage, Donation $donation = null ): self {
		return new self( '', $successMessage, $donation );
	}

	public static function newFailureResponse( string $errorMessage, Donation $donation = null ): self {
		return new self( $errorMessage, '', $donation );
	}

	private function __construct( string $errorMessage = '', string $successMessage = '', Donation $donation = null ) {
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
