<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ListComments;

class CommentListingRequest {

	public const FIRST_PAGE = 1;

	public function __construct( private readonly int $limit, private readonly int $page ) {
	}

	public function getLimit(): int {
		return $this->limit;
	}

	public function getPage(): int {
		return $this->page;
	}

}
