<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Infrastructure;

use WMDE\Fundraising\Frontend\MembershipContext\Domain\Model\EmailAddress;

interface TemplateMailerInterface {

	/**
	 * @param EmailAddress $recipient The recipient of the email to send
	 * @param array $templateArguments Context parameters to use while rendering the template
	 */
	public function sendMail( EmailAddress $recipient, array $templateArguments = [] ): void;
}
