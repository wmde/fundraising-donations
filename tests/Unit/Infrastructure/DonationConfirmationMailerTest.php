<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationMailer;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingTemplateMailer;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\Infrastructure\DonationMailer
 */
class DonationConfirmationMailerTest extends TestCase {

	private const ADMIN_EMAIL = 'picard@starfleet.com';

	public function testTemplateDataContainsAllNecessaryDonationInformation(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$donation = ValidDonation::newBankTransferDonation();
		$paymentService = $this->getMockPaymentService( $donation->getPaymentId(), ValidPayments::newBankTransferPayment()->getDisplayValues() );

		$confirmationMailer = new DonationMailer( $mailerSpy, new ThrowingTemplateMailer(), $paymentService, self::ADMIN_EMAIL );

		$confirmationMailer->sendConfirmationFor( $donation );

		$mailerSpy->assertCalledOnceWith( new EmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS ), [
			'recipient' => [
				'firstName' => ValidDonation::DONOR_FIRST_NAME,
				'lastName' => ValidDonation::DONOR_LAST_NAME,
				'salutation' => ValidDonation::DONOR_SALUTATION,
				'title' => ValidDonation::DONOR_TITLE
			],
			'donation' => [
				'id' => $donation->getId(),
				'amount' => ValidDonation::DONATION_AMOUNT,
				'amountInCents' => intval( ValidDonation::DONATION_AMOUNT * 100 ),
				'interval' => ValidDonation::PAYMENT_INTERVAL_IN_MONTHS,
				'needsModeration' => false,
				'paymentType' => 'UEB',
				'bankTransferCode' => ValidPayments::PAYMENT_BANK_TRANSFER_CODE,
				'receiptOptIn' => true,
				'moderationFlags' => [],
			]
		] );
	}

	public function testTemplateDataContainsModerationInformation(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration(
			new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH, 'amount' ),
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' ),
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'street' ),
		);
		$confirmationMailer = new DonationMailer(
			$mailerSpy,
			new ThrowingTemplateMailer(),
			$this->getMockPaymentService( $donation->getPaymentId(), ValidPayments::newBankTransferPayment()->getDisplayValues() ),
			self::ADMIN_EMAIL
		);

		$confirmationMailer->sendConfirmationFor( $donation );

		[ , $templateArguments ] = $mailerSpy->getSendMailCalls()[0];
		if ( !is_array( $templateArguments['donation'] ) ) {
			$templateArguments['donation'] = [];
		}
		$this->assertTrue( $templateArguments['donation']['needsModeration'] );
		$this->assertSame(
			[
			'AMOUNT_TOO_HIGH' => true,
			'ADDRESS_CONTENT_VIOLATION' => true
			],
			$templateArguments['donation']['moderationFlags']
		);
	}

	public function testGivenAnonymousDonationMailerDoesNothing(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$donation = ValidDonation::newBookedAnonymousPayPalDonation();
		$confirmationMailer = new DonationMailer( $mailerSpy, new ThrowingTemplateMailer(), $this->createStub( GetPaymentUseCase::class ), self::ADMIN_EMAIL );

		$confirmationMailer->sendConfirmationFor( $donation );

		$this->assertCount( 0, $mailerSpy->getSendMailCalls(), 'Mailer should not get any calls' );
	}

	public function testGivenUnmoderatedDonation_adminIsNotNotified(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$confirmationMailer = new DonationMailer( new ThrowingTemplateMailer(), $mailerSpy,  $this->createStub( GetPaymentUseCase::class ), self::ADMIN_EMAIL );
		$donation = ValidDonation::newDirectDebitDonation();

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );

		$this->assertCount( 0, $mailerSpy->getSendMailCalls() );
	}

	/**
	 * @param ModerationReason[] $moderationReasons
	 * @param int $expectedMailCount
	 *
	 * @return void
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @dataProvider moderationReasonProvider
	 */
	public function testGivenModeratedDonation_adminIsNotNotifiedOfAnyModerationExceptAmountTooHigh( array $moderationReasons, int $expectedMailCount ): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( ...$moderationReasons );
		$paymentService = $this->createStub( GetPaymentUseCase::class );
		$paymentService->method( 'getPaymentDataArray' )->willReturn( ValidPayments::newDirectDebitPayment()->getDisplayValues() );
		$confirmationMailer = new DonationMailer(
			new ThrowingTemplateMailer(),
			$mailerSpy,
			$paymentService,
			self::ADMIN_EMAIL
		);

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );

		$this->assertCount( $expectedMailCount, $mailerSpy->getSendMailCalls() );
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
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH, 'amount' ) );
		$confirmationMailer = new DonationMailer(
			new ThrowingTemplateMailer(),
			$mailerSpy,
			$this->getMockPaymentService( $donation->getPaymentId(), ValidPayments::newBankTransferPayment()->getDisplayValues() ),
			self::ADMIN_EMAIL
		);

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );

		$mailerSpy->assertCalledOnceWith( new EmailAddress( self::ADMIN_EMAIL ), [
			'id' => $donation->getId(),
			'amount' => ValidDonation::DONATION_AMOUNT,
			'moderationFlags' => [
				'AMOUNT_TOO_HIGH' => true,
			],
		] );
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
