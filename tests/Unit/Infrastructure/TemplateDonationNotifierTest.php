<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Infrastructure\AdminNotificationInterface;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsAdmin;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsDonation;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsDonationConfirmation;
use WMDE\Fundraising\DonationContext\Infrastructure\TemplateDonationNotifier;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonorNotificationSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingAdminNotifier;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingDonorNotification;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\Infrastructure\TemplateDonationNotifier
 */
class TemplateDonationNotifierTest extends TestCase {

	private const ADMIN_EMAIL = 'picard@starfleet.com';

	public function testTemplateDataContainsAllNecessaryDonationInformation(): void {
		$mailerSpy = new DonorNotificationSpy( $this );
		$donation = ValidDonation::newBankTransferDonation();
		$paymentService = $this->getMockPaymentService( $donation->getPaymentId(), ValidPayments::newBankTransferPayment()->getDisplayValues() );
		$confirmationMailer = new TemplateDonationNotifier( $mailerSpy, new ThrowingAdminNotifier(), $paymentService, self::ADMIN_EMAIL );
		$confirmationMailer->sendConfirmationFor( $donation );

		$mailerSpy->assertCalledOnceWith(
			new EmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS ),
			new TemplateArgumentsDonationConfirmation(
				[
					'firstName' => ValidDonation::DONOR_FIRST_NAME,
					'lastName' => ValidDonation::DONOR_LAST_NAME,
					'salutation' => ValidDonation::DONOR_SALUTATION,
					'title' => ValidDonation::DONOR_TITLE
				],
				new TemplateArgumentsDonation(
					id: $donation->getId(),
					amount: ValidDonation::DONATION_AMOUNT,
					amountInCents: intval( ValidDonation::DONATION_AMOUNT * 100 ),
					interval: ValidDonation::PAYMENT_INTERVAL_IN_MONTHS,
					paymentType: 'UEB',
					needsModeration: false,
					moderationFlags: [],
					bankTransferCode: ValidPayments::PAYMENT_BANK_TRANSFER_CODE,
					receiptOptIn: true,
				)
			)
		);
	}

	public function testTemplateDataContainsModerationInformation(): void {
		$mailerSpy = new DonorNotificationSpy( $this );
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration(
			new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH, 'amount' ),
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' ),
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'street' ),
		);
		$confirmationMailer = new TemplateDonationNotifier(
			$mailerSpy,
			new ThrowingAdminNotifier(),
			$this->getMockPaymentService( $donation->getPaymentId(), ValidPayments::newBankTransferPayment()->getDisplayValues() ),
			self::ADMIN_EMAIL
		);

		$confirmationMailer->sendConfirmationFor( $donation );

		[ , $templateArguments ] = $mailerSpy->getSendMailCalls()[0];
		/** @var TemplateArgumentsDonation $donation */
		$donation = $templateArguments->donation;
		$this->assertTrue( $donation->needsModeration );
		$this->assertSame(
			[
			'AMOUNT_TOO_HIGH' => true,
			'ADDRESS_CONTENT_VIOLATION' => true
			],
			$donation->moderationFlags
		);
	}

	public function testGivenAnonymousDonationMailerDoesNothing(): void {
		$mailerSpy = new DonorNotificationSpy( $this );
		$donation = ValidDonation::newBookedAnonymousPayPalDonation();
		$confirmationMailer = new TemplateDonationNotifier( $mailerSpy, new ThrowingAdminNotifier(), $this->createStub( GetPaymentUseCase::class ), self::ADMIN_EMAIL );

		$confirmationMailer->sendConfirmationFor( $donation );

		$this->assertCount( 0, $mailerSpy->getSendMailCalls(), 'Mailer should not get any calls' );
	}

	public function testGivenUnmoderatedDonation_adminIsNotNotified(): void {
		$mailerSpy = $this->createMock( AdminNotificationInterface::class );
		$mailerSpy->expects( $this->never() )->method( 'sendMail' );

		$confirmationMailer = new TemplateDonationNotifier( new ThrowingDonorNotification(), $mailerSpy,  $this->createStub( GetPaymentUseCase::class ), self::ADMIN_EMAIL );
		$donation = ValidDonation::newDirectDebitDonation();

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );
	}

	/**
	 * @param ModerationReason[] $moderationReasons
	 * @param int $expectedMailCount
	 *
	 * @return void
	 * @dataProvider moderationReasonProvider
	 */
	public function testGivenModeratedDonation_adminIsNotNotifiedOfAnyModerationExceptAmountTooHigh( array $moderationReasons, int $expectedMailCount ): void {
		$mailerSpy = $this->createMock( AdminNotificationInterface::class );
		$mailerSpy->expects( $this->exactly( $expectedMailCount ) )->method( 'sendMail' );

		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( ...$moderationReasons );
		$paymentService = $this->createStub( GetPaymentUseCase::class );
		$paymentService->method( 'getPaymentDataArray' )->willReturn( ValidPayments::newDirectDebitPayment()->getDisplayValues() );
		$confirmationMailer = new TemplateDonationNotifier(
			new ThrowingDonorNotification(),
			$mailerSpy,
			$paymentService,
			self::ADMIN_EMAIL
		);

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );
	}

	/**
	 * @return iterable<array{ModerationReason[], int}>
	 */
	public static function moderationReasonProvider(): iterable {
		yield 'address content violation' => [ [ new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ) ], 0 ];
		yield 'multiple violations, but not amount one' => [ [
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ),
			new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN )
		], 0 ];
		yield 'amount violation' => [ [ new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH ) ], 1 ];
		yield 'amount violations and others' => [ [
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ),
			new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH )
		], 1 ];
	}

	public function testTemplateDataForAdminContainsAllNecessaryDonationInformation(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH, 'amount' ) );
		$expectedTemplateArguments = new TemplateArgumentsAdmin(
			donationId: $donation->getId(),
			moderationFlags: [
				'AMOUNT_TOO_HIGH' => true,
			],
			amount: ValidDonation::DONATION_AMOUNT,
		);
		$expectedEmail = new EmailAddress( self::ADMIN_EMAIL );
		$mailerSpy = $this->createMock( AdminNotificationInterface::class );
		$mailerSpy->expects( $this->once() )->method( 'sendMail' )->with( $expectedEmail, $expectedTemplateArguments );

		$confirmationMailer = new TemplateDonationNotifier(
			new ThrowingDonorNotification(),
			$mailerSpy,
			$this->getMockPaymentService( $donation->getPaymentId(), ValidPayments::newBankTransferPayment()->getDisplayValues() ),
			self::ADMIN_EMAIL
		);

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );
	}

	/**
	 * @param int $paymentId
	 * @param array<string,mixed> $returnTemplateData
	 * @return GetPaymentUseCase
	 */
	private function getMockPaymentService( int $paymentId, array $returnTemplateData ): GetPaymentUseCase {
		$paymentService = $this->createMock( GetPaymentUseCase::class );
		$paymentService->expects( $this->once() )
			->method( 'getPaymentDataArray' )
			->with( $paymentId )
			->willReturn( $returnTemplateData );
		return $paymentService;
	}
}
