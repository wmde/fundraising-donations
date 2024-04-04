<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsDonationConfirmation;
use WMDE\Fundraising\DonationContext\Infrastructure\DonorNotificationInterface;

class DonorNotificationSpy implements DonorNotificationInterface {

	private TestCase $testCase;

	/**
	 * @var array{EmailAddress,TemplateArgumentsDonationConfirmation}[]
	 */
	private array $sendMailCalls = [];

	public function __construct( TestCase $testCase ) {
		$this->testCase = $testCase;
	}

	public function sendMail( EmailAddress $recipient, TemplateArgumentsDonationConfirmation $templateArguments ): void {
		$this->sendMailCalls[] = [ $recipient, $templateArguments ];
	}

	/**
	 * @return array{EmailAddress,TemplateArgumentsDonationConfirmation}[]
	 */
	public function getSendMailCalls(): array {
		return $this->sendMailCalls;
	}

	public function assertCalledOnceWith( EmailAddress $expectedEmail, TemplateArgumentsDonationConfirmation $expectedArguments ): void {
		$this->assertCalledOnce();

		$this->testCase->assertEquals(
			[
				$expectedEmail,
				$expectedArguments
			],
			$this->sendMailCalls[0]
		);
	}

	public function assertCalledOnce(): void {
		$this->testCase->assertCount( 1, $this->sendMailCalls, 'Mailer should be called exactly once' );
	}

}
