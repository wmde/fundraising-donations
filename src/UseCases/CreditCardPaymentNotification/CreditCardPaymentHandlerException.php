<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CreditCardPaymentHandlerException extends \RuntimeException {

	public function __construct( string $message, \Exception $previous = null ) {
		parent::__construct( $message, 0, $previous );
	}

}
