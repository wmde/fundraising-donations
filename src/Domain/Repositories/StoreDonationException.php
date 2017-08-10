<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories;

/**
 * @license GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class StoreDonationException extends \RuntimeException {

	public function __construct( \Exception $previous = null, string $message = 'Could not store donation' ) {
		parent::__construct( $message, 0, $previous );
	}

}
