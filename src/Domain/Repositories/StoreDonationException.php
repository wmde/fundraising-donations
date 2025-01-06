<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

use Throwable;

class StoreDonationException extends \RuntimeException {

	public function __construct( ?Throwable $previous = null, string $message = 'Could not store donation' ) {
		parent::__construct( $message, 0, $previous );
	}

}
