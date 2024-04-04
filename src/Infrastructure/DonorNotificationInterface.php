<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsDonationConfirmation;

interface DonorNotificationInterface {

	public function sendMail( EmailAddress $recipient, TemplateArgumentsDonationConfirmation $templateArguments ): void;
}
