<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\GetDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class GetDonationResponse {

	public static function newNotAllowedResponse(): self {
		return new self( null );
	}

	public static function newValidResponse( Donation $donation, string $updateToken ): self {
		return new self( $donation, $updateToken );
	}

	private $donation;
	private $updateToken;

	private function __construct( Donation $donation = null, string $updateToken = null ) {
		$this->donation = $donation;
		$this->updateToken = $updateToken;
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

	public function getUpdateToken(): ?string {
		return $this->updateToken;
	}

}
