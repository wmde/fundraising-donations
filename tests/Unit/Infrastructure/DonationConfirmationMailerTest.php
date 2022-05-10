<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer
 */
class DonationConfirmationMailerTest extends TestCase {

	public function testTemplateDataContainsAllNecessaryDonationInformation(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$donation = ValidDonation::newBankTransferDonation();
		$paymentService = $this->createMock( GetPaymentUseCase::class );
		$paymentService->expects($this->once() )
			->method( 'getPaymentDataArray' )
			->with( $donation->getPaymentId() )
			->willReturn(
		[
				'amount' => ValidDonation::DONATION_AMOUNT,
				'interval' => ValidDonation::PAYMENT_INTERVAL_IN_MONTHS,
				'paymentType' => 'UEB',
				'ueb_code' => ValidPayments::PAYMENT_BANK_TRANSFER_CODE
			]
		);
		$confirmationMailer = new DonationConfirmationMailer( $mailerSpy, $paymentService );

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
				'bankTransferCode' => ValidPayments::PAYMENT_BANK_TRANSFER_CODE,
				'receiptOptIn' => null,
			]
		] );
	}

	public function testGivenAnonymousDonationMailerDoesNothing(): void {
		$mailerSpy = new TemplateBasedMailerSpy( $this );
		$confirmationMailer = new DonationConfirmationMailer( $mailerSpy );
		$donation = ValidDonation::newBookedAnonymousPayPalDonation();

		$confirmationMailer->sendConfirmationFor( $donation );

		$this->assertCount( 0, $mailerSpy->getSendMailCalls(), 'Mailer should not get any calls' );
	}

}
