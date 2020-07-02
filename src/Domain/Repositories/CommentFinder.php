<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface CommentFinder {

	/**
	 * Returns the comments that can be shown to non-privileged users, newest first.
	 *
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return CommentWithAmount[]
	 */
	public function getPublicComments( int $limit, int $offset = 0 ): array;

}
