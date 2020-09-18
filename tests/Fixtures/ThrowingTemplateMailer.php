<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\TemplateMailerInterface;

class ThrowingTemplateMailer implements TemplateMailerInterface {

	public function sendMail( EmailAddress $recipient, array $templateArguments = [] ): void {
		throw new \Exception( 'Error while sending mail' );
	}

}
