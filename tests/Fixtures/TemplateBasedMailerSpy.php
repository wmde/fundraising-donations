<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\TemplateMailerInterface;

class TemplateBasedMailerSpy implements TemplateMailerInterface {

	private TestCase $testCase;
	/**
	 * @var array<array{EmailAddress,array<string,mixed>}>
	 */
	private array $sendMailCalls = [];

	public function __construct( TestCase $testCase ) {
		$this->testCase = $testCase;
	}

	public function sendMail( EmailAddress $recipient, array $templateArguments = [] ): void {
		$this->sendMailCalls[] = [ $recipient, $templateArguments ];
	}

	/**
	 * @return array<array{EmailAddress,array<string,mixed>}>
	 */
	public function getSendMailCalls(): array {
		return $this->sendMailCalls;
	}

	/**
	 * @param EmailAddress $expectedEmail
	 * @param array<string,mixed> $expectedArguments
	 *
	 * @return void
	 */
	public function assertCalledOnceWith( EmailAddress $expectedEmail, array $expectedArguments ): void {
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
