<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

use RuntimeException;
use Throwable;

class GetDonationException extends RuntimeException {

	public function __construct( Throwable $previous = null, string $message = 'Could not get donation' ) {
		parent::__construct( $message, 0, $previous );
	}

}
