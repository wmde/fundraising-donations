<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Domain;

/**
 * Scrub personal data from donations, writing them back to the persistence layer.
 */
interface DonationAnonymizer {

	/**
	 * Anonymize individual donations
	 *
	 * @throws AnonymizationException
	 */
	public function anonymizeWithIds( int ...$donationIds ): void;

	/**
	 * Anonymize all donations that have been exported or are older than 2 days
	 *
	 * @return int number of anonymized rows
	 * @throws AnonymizationException
	 */
	public function anonymizeAll(): int;
}
