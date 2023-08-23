<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Authorization;

/**
 * @deprecated
 */
class DonationTokenFetchingException extends \RuntimeException {

	public function __construct( string $message, \Exception $previous = null ) {
		parent::__construct( $message, 0, $previous );
	}

}
