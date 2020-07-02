<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

/**
 * Logs information on changes to the donation during its lifecycle.
 *
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
interface DonationEventLogger {

	/**
	 * @param int $donationId
	 * @param string $message
	 *
	 * @throws DonationEventLogException
	 */
	public function log( int $donationId, string $message ): void;

}
