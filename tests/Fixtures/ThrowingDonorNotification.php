<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsDonationConfirmation;
use WMDE\Fundraising\DonationContext\Infrastructure\DonorNotificationInterface;

class ThrowingDonorNotification implements DonorNotificationInterface {

	public function sendMail( EmailAddress $recipient, TemplateArgumentsDonationConfirmation $templateArguments ): void {
		throw new \Exception( 'Error while sending mail' );
	}

}
