<?php

namespace WMDE\Fundraising\DonationContext\UseCases;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;

/**
 * Send notification texts (e.g. via email) for specific receivers and donation states
 */
interface DonationNotifier {
	public function sendConfirmationFor( Donation $donation ): void;

	public function sendModerationNotificationToAdmin( Donation $donation ): void;
}
