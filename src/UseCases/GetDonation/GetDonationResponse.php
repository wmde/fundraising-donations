<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\UseCases\GetDonation;

use WMDE\Fundraising\Frontend\DonationContext\Domain\Model\Donation;

/**
 * @license GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class GetDonationResponse {

	public static function newNotAllowedResponse(): self {
		return new self();
	}

	public static function newValidResponse( Donation $donation, string $updateToken ): self {
		return new self( $donation, $updateToken );
	}

	private $donationId;
	private $donationStatus;

	private $paymentAmount;
	private $paymentMethod;
	private $paymentInterval;

	private $donorType;
	private $donorFirstName;
	private $donorLastName;
	private $donorSalutation;
	private $donorTitle;
	private $donorCompany;
	private $donorStreetAddress;
	private $donorPostalCode;
	private $donorCity;
	private $donorCountryCode;
	private $donorEmailAddress;
	private $donorOptsIntoNewsletter;



	private $updateToken;
	private $accessToken;



	public function accessIsPermitted(): bool {
		return $this->donation !== null;
	}

}