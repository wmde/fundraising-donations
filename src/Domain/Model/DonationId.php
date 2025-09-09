<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

/**
 * This class and its Doctrine mapping are only used in the test environment to quickly create and
 * tear down the last_generated_donation_id table. The production environment uses a migration to set up the table.
 *
 * When setting up a test environment that needs to generate donation IDs in the database,
 * you must insert one DonationId into the table. The easiest way to accomplish this is to run
 *
 * ```php
 * $entityManager->persist( new DonationId() );
 * $entityManager->flush();
 * ```
 *
 * @codeCoverageIgnore
 */
class DonationId {

	/**
	 * used for doctrine mapping only
	 */
	// @phpstan-ignore-next-line property.unusedType
	private ?int $id = null;
	private int $donationId;

	public function __construct( int $donationId = 0 ) {
		$this->donationId = $donationId;
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}
}
