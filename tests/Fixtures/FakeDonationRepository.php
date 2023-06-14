<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class FakeDonationRepository implements DonationRepository {
	/**
	 * @var Donation[]
	 */
	private array $donations = [];

	/**
	 * @var Donation[]
	 */
	private array $donationClones = [];
	private bool $throwOnRead = false;
	private bool $throwOnWrite = false;

	public function __construct( Donation ...$donations ) {
		foreach ( $donations as $donation ) {
			$this->storeDonation( $donation );
		}
	}

	public function throwOnRead(): void {
		$this->throwOnRead = true;
	}

	public function throwOnWrite(): void {
		$this->throwOnWrite = true;
	}

	public function storeDonation( Donation $donation ): void {
		if ( $this->throwOnWrite ) {
			throw new StoreDonationException();
		}

		$this->donations[ $donation->getId() ] = $donation;
		// guard against memory-modification after store
		$this->donationClones[ $donation->getId() ] = clone $donation;
	}

	/**
	 * @return Donation[]
	 */
	public function getDonations(): array {
		return $this->donations;
	}

	public function getDonationById( int $id ): ?Donation {
		if ( $this->throwOnRead ) {
			throw new GetDonationException();
		}

		if ( array_key_exists( $id, $this->donations ) ) {
			if ( serialize( $this->donationClones[$id] ) !== serialize( $this->donations[$id] ) ) {
				// return object value at the time of storing
				return $this->donationClones[$id];
			}
			// return original object (===)
			return $this->donations[$id];
		}

		return null;
	}

}
