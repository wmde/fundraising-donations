<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class StoreDonationException extends \RuntimeException {

	public function __construct( \Exception $previous = null, string $message = 'Could not store donation' ) {
		parent::__construct( $message, 0, $previous );
	}

}
