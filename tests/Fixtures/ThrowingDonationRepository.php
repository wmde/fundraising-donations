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
class ThrowingDonationRepository implements DonationRepository {

	private $throwOnStoreDonation;
	private $onGetDonationById;

	public function throwOnStoreDonation(): void {
		$this->throwOnStoreDonation = true;
	}

	public function throwOnGetDonationById(): void {
		$this->onGetDonationById = true;
	}

	/**
	 * When storing a not yet persisted Donation, a new id will be generated and assigned to it.
	 * This means the id of new donations needs to be null. The id can be accessed by calling getId on
	 * the passed in Donation.
	 *
	 * @param Donation $donation
	 *
	 * @throws StoreDonationException
	 */
	public function storeDonation( Donation $donation ): void {
		if ( $this->throwOnStoreDonation ) {
			throw new StoreDonationException();
		}
	}

	/**
	 * @param int $id
	 *
	 * @return Donation|null
	 * @throws GetDonationException
	 */
	public function getDonationById( int $id ): ?Donation {
		if ( $this->onGetDonationById ) {
			throw new GetDonationException();
		}

		return null;
	}

}
