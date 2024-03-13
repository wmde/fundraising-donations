<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ListComments;

use WMDE\Fundraising\DonationContext\Domain\ReadModel\Comment;

class CommentList {

	/**
	 * @var Comment[]
	 */
	private readonly array $comments;

	public function __construct( Comment ...$comments ) {
		$this->comments = $comments;
	}

	/**
	 * @return Comment[]
	 */
	public function toArray(): array {
		return $this->comments;
	}

}
