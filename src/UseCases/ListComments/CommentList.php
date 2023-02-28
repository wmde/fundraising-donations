<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ListComments;

use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentWithAmount;

class CommentList {

	/**
	 * @var CommentWithAmount[]
	 */
	private readonly array $comments;

	public function __construct( CommentWithAmount ...$comments ) {
		$this->comments = $comments;
	}

	/**
	 * @return CommentWithAmount[]
	 */
	public function toArray(): array {
		return $this->comments;
	}

}
