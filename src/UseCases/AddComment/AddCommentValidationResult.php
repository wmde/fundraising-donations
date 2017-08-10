<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddCommentValidationResult {

	public const VIOLATION_NAME_TOO_LONG = 'comment_failure_name_too_long';
	public const VIOLATION_COMMENT_TOO_LONG = 'comment_failure_text_too_long';

	public const SOURCE_COMMENT = 'kommentar';
	public const SOURCE_NAME = 'eintrag';

	private $violations;

	/**
	 * @param string[] $violations AddCommentValidationResult::SOURCE_ => AddCommentValidationResult::VIOLATION_
	 */
	public function __construct( array $violations = [] ) {
		$this->violations = $violations;
	}

	public function getViolations(): array {
		return $this->violations;
	}

	public function isSuccessful(): bool {
		return empty( $this->violations );
	}

	public function getFirstViolation(): string {
		if ( empty( $this->violations ) ) {
			throw new \RuntimeException( 'There are not validation errors.' );
		}
		return reset( $this->violations );
	}

}