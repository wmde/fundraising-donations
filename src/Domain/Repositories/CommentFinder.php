<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

use WMDE\Fundraising\DonationContext\Domain\ReadModel\Comment;

interface CommentFinder {

	/**
	 * Returns the comments that can be shown to non-privileged users, newest first.
	 *
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return Comment[]
	 */
	public function getPublicComments( int $limit, int $offset = 0 ): array;

}
