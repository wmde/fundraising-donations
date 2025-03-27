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
	 * @deprecated We no longer want to anonymise by date, but rather all exported donations and ones older than 2 days
	 */
	public function anonymizeAt( \DateTimeImmutable $timestamp ): int;

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
