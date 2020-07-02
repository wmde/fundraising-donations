<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CancelDonationRequest {

	private $donationId;

	public function __construct( int $donationId ) {
		$this->donationId = $donationId;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

}
