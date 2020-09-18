<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;

/**
 * This mailer implementation catches all exceptions while sending email and logs them as user errors.
 *
 * All User-facing code should continue to work, even when sending emails fails.
 *
 * @license GPL-2.0-or-later
 */
class BestEffortTemplateMailer implements TemplateMailerInterface {

	private TemplateMailerInterface $mailer;

	public function __construct( TemplateMailerInterface $mailer ) {
		$this->mailer = $mailer;
	}

	public function sendMail( EmailAddress $recipient, array $templateArguments = [] ): void {
		try {
			$this->mailer->sendMail( $recipient, $templateArguments );
		} catch ( \Exception $ex ) {
			trigger_error( $ex->getMessage(), \E_USER_NOTICE );
		}
	}

}
