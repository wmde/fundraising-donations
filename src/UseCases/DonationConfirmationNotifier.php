<?php

namespace WMDE\Fundraising\DonationContext\UseCases;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;

interface DonationConfirmationNotifier {
	public function sendConfirmationFor( Donation $donation ): void;
}
