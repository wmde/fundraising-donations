<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\RestoreDonation;

class RestoreDonationFailureResponse {
	public const string DONATION_NOT_FOUND = 'Donation not found';
	public const string DONATION_NOT_CANCELED = 'Donation is not cancelled.';

	public function __construct(
		public readonly int $donationId,
		public readonly string $message
	) {
	}

}
