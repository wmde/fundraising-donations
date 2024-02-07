<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use RuntimeException;
use Throwable;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class DonationEventLogException extends RuntimeException {

	public function __construct( string $message, Throwable $previous = null ) {
		parent::__construct( $message, 0, $previous );
	}

}
