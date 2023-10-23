<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

interface DonationAuthorizationChecker {

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
