<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationMailer;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;

/**
 * @covers \WMDE\Fundraising\DonationContext\Infrastructure\DonationMailer
 */
class DonationConfirmationMailerTest extends TestCase {

	private const ADMIN_EMAIL = 'picard@starfleet.com';

	public function testTemplateDataContainsAllNecessaryDonationInformation(): void {
		$this->markTestIncomplete( 'Donation confirmation mailer needs "get payment" use case to get payment info. ' );
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$confirmationMailer = new DonationMailer( $mailerSpy, $mailerSpy, self::ADMIN_EMAIL );
		$donation = ValidDonation::newBankTransferDonation();

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
				'interval' => ValidDonation::PAYMENT_INTERVAL_IN_MONTHS,
				'needsModeration' => false,
				'paymentType' => 'UEB',
				'bankTransferCode' => ValidDonation::PAYMENT_BANK_TRANSFER_CODE,
				'receiptOptIn' => null,
				'moderationFlags' => [],
			]
		] );
	}

	public function testTemplateDataContainsModerationInformation(): void {
		$this->markTestIncomplete( 'Donation confirmation mailer needs "get payment" use case to get payment info. ' );
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$confirmationMailer = new DonationMailer( $mailerSpy, $mailerSpy, self::ADMIN_EMAIL );
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration(
			new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH, 'amount' ),
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' ),
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'street' ),
		);

		$confirmationMailer->sendConfirmationFor( $donation );

		[ ,$templateArguments ] = $mailerSpy->getSendMailCalls()[0];
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
		$confirmationMailer = new DonationMailer( $mailerSpy, $mailerSpy, self::ADMIN_EMAIL );
		$donation = ValidDonation::newBookedAnonymousPayPalDonation();

		$confirmationMailer->sendConfirmationFor( $donation );

		$this->assertCount( 0, $mailerSpy->getSendMailCalls(), 'Mailer should not get any calls' );
	}

	public function testGivenUnmoderatedDonation_adminIsNotNotified(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$confirmationMailer = new DonationMailer( $mailerSpy, $mailerSpy, self::ADMIN_EMAIL );
		$donation = ValidDonation::newDirectDebitDonation();

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );

		$this->assertCount( 0, $mailerSpy->getSendMailCalls() );
	}

	/**
	 * @dataProvider moderationReasonProvider
	 */
	public function testGivenModeratedDonation_adminIsNotNotifiedOfAnyModerationExceptAmountTooHigh( array $moderationReasons, int $expectedMailCount ): void {
		$this->markTestIncomplete( 'Donation confirmation mailer needs "get payment" use case to get payment info. ' );
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$confirmationMailer = new DonationMailer( $mailerSpy, $mailerSpy, self::ADMIN_EMAIL );
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( ...$moderationReasons );

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );

		$this->assertCount( $expectedMailCount, $mailerSpy->getSendMailCalls() );
	}

	public function moderationReasonProvider(): iterable {
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
		$this->markTestIncomplete( 'Donation confirmation mailer needs "get payment" use case to get payment info. ' );
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$confirmationMailer = new DonationMailer( $mailerSpy, $mailerSpy, self::ADMIN_EMAIL );
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH, 'amount' ) );

		$confirmationMailer->sendModerationNotificationToAdmin( $donation );

		$mailerSpy->assertCalledOnceWith( new EmailAddress( self::ADMIN_EMAIL ), [
			'id' => $donation->getId(),
			'amount' => ValidDonation::DONATION_AMOUNT,
			'moderationFlags' => [
				'AMOUNT_TOO_HIGH' => true,
			],
		] );
	}
}
