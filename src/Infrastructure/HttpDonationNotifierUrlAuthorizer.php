<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

interface HttpDonationNotifierUrlAuthorizer {

	/**
	 * @param int $donationId
	 * @param array<string,mixed> $parameters
	 * @return array<string,mixed>
	 */
	public function addAuthorizationParameters( int $donationId, array $parameters ): array;
}
