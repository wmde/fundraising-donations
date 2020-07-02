<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ListComments;

use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentWithAmount;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CommentList {

	private $comments;

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
