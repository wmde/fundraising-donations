<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\GetDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;

class GetDonationResponse {

	private ?Donation $donation;

	public static function newNotAllowedResponse(): self {
		return new self( null );
	}

	public static function newValidResponse( Donation $donation ): self {
		return new self( $donation );
	}

	private function __construct( Donation $donation = null ) {
		$this->donation = $donation;
	}

	/**
	 * Returns the Donation when @see accessIsPermitted returns true, or null otherwise.
	 *
	 * ATTENTION: We're returning the domain object in order to avoid a verbose read-only response model.
	 * Keep in mind that your presenters should only query the domain object
	 * and NOT call any of its state-changing methods
	 *
	 * @return Donation|null
	 */
	public function getDonation(): ?Donation {
		return $this->donation;
	}

	public function accessIsPermitted(): bool {
		return $this->donation !== null;
	}

}
