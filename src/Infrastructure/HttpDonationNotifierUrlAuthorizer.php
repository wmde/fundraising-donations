<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

interface HttpDonationNotifierUrlAuthorizer {

	/**
	 * @param int $donationId
	 * @param array<string,mixed> $queryParameters URL query parameters
	 * @return array<string,mixed> Modified URL query parameters with added security information, e.g. "token", "utoken", etc
	 */
	public function addAuthorizationParameters( int $donationId, array $queryParameters ): array;
}
