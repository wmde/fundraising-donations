<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use RuntimeException;
use Throwable;

class DonationEventLogException extends RuntimeException {

	public function __construct( string $message, ?Throwable $previous = null ) {
		parent::__construct( $message, 0, $previous );
	}

}
