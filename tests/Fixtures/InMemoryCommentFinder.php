<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\ReadModel\Comment;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentFinder;

class InMemoryCommentFinder implements CommentFinder {

	/**
	 * @var Comment[]
	 */
	private array $comments;

	public function __construct( Comment ...$comments ) {
		$this->comments = $comments;
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return Comment[]
	 */
	public function getPublicComments( int $limit, int $offset = 0 ): array {
		return array_slice( $this->comments, $offset, $limit );
	}

}
