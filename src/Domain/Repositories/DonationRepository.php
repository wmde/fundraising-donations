<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories;

use WMDE\Fundraising\Frontend\DonationContext\Domain\Model\Donation;

/**
 * @license GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface DonationRepository {

	/**
	 * When storing a not yet persisted Donation, a new id will be generated and assigned to it.
	 * This means the id of new donations needs to be null. The id can be accessed by calling getId on
	 * the passed in Donation.
	 *
	 * @param Donation $donation
	 *
	 * @throws StoreDonationException
	 */
	public function storeDonation( Donation $donation ): void;

	/**
	 * @param int $id
	 *
	 * @return Donation|null
	 * @throws GetDonationException
	 */
	public function getDonationById( int $id ): ?Donation;

}
