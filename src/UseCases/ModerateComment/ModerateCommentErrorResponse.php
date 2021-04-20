<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ModerateComment;

class ModerateCommentErrorResponse implements ModerateCommentResponse {

	public const ERROR_DONATION_NOT_FOUND = 'donation_not_found';
	public const ERROR_DONATION_HAS_NO_COMMENT = 'donation_has_no_comment';

	private string $error;

	public function __construct( string $error ) {
		$this->error = $error;
	}

	public function getError(): string {
		return $this->error;
	}
}
