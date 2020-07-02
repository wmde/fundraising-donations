<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\GetDonation;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class GetDonationRequest {

	private $donationId;

	public function __construct( int $donationId ) {
		$this->donationId = $donationId;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

}
