<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\BestEffortTemplateMailer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingTemplateMailer;

/**
 * @covers \WMDE\Fundraising\DonationContext\Infrastructure\BestEffortTemplateMailer
 */
class BestEffortTemplateMailerTest extends TestCase {

	public function testGivenNormalOperationMailsArePassedOn(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$mailer = new BestEffortTemplateMailer( $mailerSpy );

		$mailer->sendMail( new EmailAddress( 'abe@wh.gov' ), [ 'name' => 'Abe Lincoln' ] );

		$mailerSpy->assertCalledOnceWith( new EmailAddress( 'abe@wh.gov' ), [ 'name' => 'Abe Lincoln' ] );
	}

	public function testGivenMailerExceptionItConvertsExceptionMessageToNotice(): void {
		$mailer = new BestEffortTemplateMailer( new ThrowingTemplateMailer() );

		$this->expectNotice();

		$mailer->sendMail( new EmailAddress( 'abe@wh.gov' ), [ 'name' => 'Abe Lincoln' ] );
	}

}
