<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

/**
 * Logs information on changes to the donation during its lifecycle.
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
