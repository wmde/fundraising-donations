<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentFinder;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentWithAmount;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class InMemoryCommentFinder implements CommentFinder {

	/**
	 * @var CommentWithAmount[]
	 */
	private array $comments;

	public function __construct( CommentWithAmount ...$comments ) {
		$this->comments = $comments;
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return CommentWithAmount[]
	 */
	public function getPublicComments( int $limit, int $offset = 0 ): array {
		return array_slice( $this->comments, $offset, $limit );
	}

}
