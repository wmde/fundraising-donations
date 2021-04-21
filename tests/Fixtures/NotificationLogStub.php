<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\NotificationLog;

class NotificationLogStub implements NotificationLog {

	public function hasSentConfirmationFor( int $donationId ): bool {
		return false;
	}

	public function logConfirmationSent( int $donationId ): void {
		// Do nothing in stub
	}
}
