<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddComment;

class AddCommentRequest {

	public readonly string $commentText;

	public function __construct(
		string $commentText,
		public readonly bool $isPublic,
		public readonly bool $isAnonymous,
		public readonly int $donationId
	) {
		$this->commentText = trim( $commentText );
	}
}
