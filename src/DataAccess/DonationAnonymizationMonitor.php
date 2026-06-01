<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

/**
 * This class contains methods to monitor the amount of older donations in the database which still
 * contain private data.
 * We use them to check whether our private data scrubbing processes work correctly.
 */
interface DonationAnonymizationMonitor {

	/**
	 * @return int amount of old donations that are still marked
	 * as moderated in the database and need to get their status resolved. Resolving their status is needed to scrub them.
	 */
	public function countOldAbandonedModeratedDonations(): int;
}
