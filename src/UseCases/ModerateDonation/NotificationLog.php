<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ModerateDonation;

interface NotificationLog {
	public function hasSentConfirmationFor( int $donationId ): bool;

	public function logConfirmationSent( int $donationId ): void;
}
