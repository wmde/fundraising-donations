<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Domain;

/**
 * Scrub personal data from donations, writing them back to the persistence layer.
 */
interface DonationAnonymizer {
	/**
	 * Anonymize donations with the matching timestamp.
	 *
	 * This is for nightly batch updates we use external script (that exports data sets and then updates all donations
	 * with a timestamp that acts as the identifier of the donation batch that should be anonymized).
	 * The timestamp is NOT the export date!
	 *
	 * Ideally, implementations won't use ORM entities but SQL UPDATE or DELETE queries.
	 *
	 * @param \DateTimeImmutable $timestamp
	 * @throws AnonymizationException
	 * @return int number of anonymized rows
	 */
	public function anonymizeAt( \DateTimeImmutable $timestamp ): int;

	/**
	 * Anonymize individual donations
	 *
	 * @throws AnonymizationException
	 */
	public function anonymizeWithIds( int ...$donationIds ): void;
}
