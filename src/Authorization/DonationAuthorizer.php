<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Authorization;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface DonationAuthorizer {

	/**
	 * Should return false on infrastructure failure.
	 *
	 * @param int $donationId
	 *
	 * @return bool
	 */
	public function userCanModifyDonation( int $donationId ): bool;

	/**
	 * Should return false on infrastructure failure.
	 *
	 * @param int $donationId
	 *
	 * @return bool
	 */
	public function systemCanModifyDonation( int $donationId ): bool;

	/**
	 * Should return false on infrastructure failure.
	 *
	 * @param int $donationId
	 *
	 * @return bool
	 */
	public function canAccessDonation( int $donationId ): bool;

}
