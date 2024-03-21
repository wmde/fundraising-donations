<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddComment;

class AddCommentResponse {

	public static function newSuccessResponse( string $successMessage ): self {
		return new self( '', $successMessage );
	}

	public static function newFailureResponse( string $errorMessage ): self {
		return new self( $errorMessage, '' );
	}

	private string $errorMessage;
	private string $successMessage;

	private function __construct( string $errorMessage, string $successMessage ) {
		$this->errorMessage = $errorMessage;
		$this->successMessage = $successMessage;
	}

	public function isSuccessful(): bool {
		return $this->errorMessage === '';
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
