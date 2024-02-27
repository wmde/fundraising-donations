<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

use RuntimeException;
use Throwable;

class CommentListingException extends RuntimeException {

	public function __construct( Throwable $previous = null ) {
		parent::__construct( 'Could not list comments', 0, $previous );
	}

}
