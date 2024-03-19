<?php

declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsAdmin;

interface AdminNotificationInterface {
	public function sendMail( EmailAddress $recipient, TemplateArgumentsAdmin $templateArguments ): void;
}
