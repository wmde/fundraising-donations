<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

use RuntimeException;
use Throwable;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CommentListingException extends RuntimeException {

	public function __construct( Throwable $previous = null ) {
		parent::__construct( 'Could not list comments', 0, $previous );
	}

}
