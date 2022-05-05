<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class DonationRepositorySpy extends FakeDonationRepository {

	/**
	 * @var Donation[]
	 */
	private array $storeDonationCalls;

	/**
	 * @var int[]
	 */
	private array $getDonationCalls = [];

	public function __construct( Donation ...$donations ) {
		parent::__construct( ...$donations );
		$this->storeDonationCalls = [];
	}

	public function storeDonation( Donation $donation ): void {
		// protect against the donation being changed later
		$this->storeDonationCalls[] = clone $donation;
		parent::storeDonation( $donation );
	}

	/**
	 * @return Donation[]
	 */
	public function getStoreDonationCalls(): array {
		return $this->storeDonationCalls;
	}

	public function noDonationsStored(): bool {
		return count( $this->storeDonationCalls ) == 0;
	}

	/**
	 * @param int $id
	 *
	 * @return Donation|null
	 * @throws GetDonationException
	 */
	public function getDonationById( int $id ): ?Donation {
		$this->getDonationCalls[] = $id;
		return parent::getDonationById( $id );
	}

	/**
	 * @return int[]
	 */
	public function getGetDonationCalls(): array {
		return $this->getDonationCalls;
	}

}
