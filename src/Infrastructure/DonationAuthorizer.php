<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\Fundraising\PaymentContext\Services\URLAuthenticator;

interface DonationAuthorizer {
	public function authorizeDonationAccess( int $donationId ): URLAuthenticator;
}
