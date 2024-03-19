<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\AdminNotificationInterface;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsAdmin;

class ThrowingAdminNotifier implements AdminNotificationInterface {

	public function sendMail( EmailAddress $emailAddress, TemplateArgumentsAdmin $templateArguments ): void {
		throw new \RuntimeException( 'Error while sending mail' );
	}
}
