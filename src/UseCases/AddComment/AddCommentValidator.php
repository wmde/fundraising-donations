<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment;

use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment\AddCommentValidationResult as Result;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddCommentValidator {

	private const MAX_COMMENT_LENGTH = 2048;

	public function validate( AddCommentRequest $request ): Result {
		$text = $request->getCommentText();

		if ( preg_replace( '/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $text ) !== $text ) {
			return new Result( [ Result::SOURCE_COMMENT => Result::VIOLATION_COMMENT_INVALID_CHARS ] );
		}

		if ( strlen( $text ) > self::MAX_COMMENT_LENGTH ) {
			return new Result( [ Result::SOURCE_COMMENT => Result::VIOLATION_COMMENT_TOO_LONG ] );
		}

		return new Result( [] );
	}
}
