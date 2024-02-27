<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\CancelDonation;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Infrastructure\TemplateMailerInterface;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\CancelPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\SuccessResponse;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationResponse
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest
 */
class CancelDonationUseCaseTest extends TestCase {

	private function newCancelDonationUseCase(
		DonationRepository $repository = null,
		TemplateMailerInterface $mailer = null,
		DonationAuthorizationChecker $authorizer = null,
		DonationEventLogger $logger = null,
		CancelPaymentUseCase $cancelPaymentUseCase = null
	): CancelDonationUseCase {
		return new CancelDonationUseCase(
			$repository ?? new FakeDonationRepository(),
			$mailer ?? new TemplateBasedMailerSpy( $this ),
			$authorizer ?? new SucceedingDonationAuthorizerSpy(),
			$logger ?? new DonationEventLoggerSpy(),
				$cancelPaymentUseCase ?? $this->getSucceedingCancelPaymentUseCase()
		);
	}

	private function getSucceedingCancelPaymentUseCase(): CancelPaymentUseCase&MockObject {
		$cancelPaymentUseCase = $this->createMock( CancelPaymentUseCase::class );
		$cancelPaymentUseCase->method( 'cancelPayment' )->willReturn( new SuccessResponse( true ) );
		return $cancelPaymentUseCase;
	}

	private function getFailingCancelPaymentUseCase(): CancelPaymentUseCase&MockObject {
		$cancelPaymentUseCase = $this->createMock( CancelPaymentUseCase::class );
		$cancelPaymentUseCase->method( 'cancelPayment' )->willReturn( new FailureResponse( "failed for whatever reason" ) );
		return $cancelPaymentUseCase;
	}

	public function testGivenIdOfUnknownDonation_cancellationIsNotSuccessful(): void {
		$response = $this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1 ) );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	public function testResponseContainsDonationId(): void {
		$response = $this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1337 ) );

		$this->assertSame( 1337, $response->getDonationId() );
	}

	public function testGivenIdOfCancellableDonation_cancellationIsSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );
		$donation = $repository->getDonationById( $donation->getId() );

		$this->assertNotNull( $donation );
		$this->assertTrue( $response->cancellationSucceeded() );
		$this->assertFalse( $response->mailDeliveryFailed() );
		$this->assertTrue( $donation->isCancelled() );
	}

	public function testGivenIdOfNonCancellableDonation_cancellationIsNotSuccessful(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase(
			repository: $repository,
			cancelPaymentUseCase: $this->getFailingCancelPaymentUseCase()
		)->cancelDonation( $request );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	private function newCancelableDonation(): Donation {
		// direct debit and bank transfer are cancelable payment types
		return ValidDonation::newDirectDebitDonation();
	}

	public function testWhenDonationGetsCancelled_cancellationConfirmationEmailIsSent(): void {
		$donation = $this->newCancelableDonation();
		$mailer = new TemplateBasedMailerSpy( $this );
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase(
			repository: $repository,
			mailer: $mailer
		)->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );
		$mailer->assertCalledOnceWith(
			new EmailAddress( $donation->getDonor()->getEmailAddress() ),
			[
				'recipient' => [
					'salutation' => ValidDonation::DONOR_SALUTATION,
					'title' => ValidDonation::DONOR_TITLE,
					'firstName' => ValidDonation::DONOR_FIRST_NAME,
					'lastName' => ValidDonation::DONOR_LAST_NAME,
				],
				'donationId' => 1
			]
		);
	}

	public function testWhenDonationGetsCancelled_logEntryNeededByBackendIsWritten(): void {
		$donation = $this->newCancelableDonation();
		$logger = new DonationEventLoggerSpy();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$this->newCancelDonationUseCase(
			repository: $repository,
			logger: $logger
		)->cancelDonation( new CancelDonationRequest( $donation->getId() ) );

		$this->assertSame(
			[ [ $donation->getId(), 'frontend: storno' ] ],
			$logger->getLogCalls()
		);
	}

	public function testWhenDonationGetsCancelledByAdmin_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = $this->newCancelableDonation();
		$logger = new DonationEventLoggerSpy();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$this->newCancelDonationUseCase(
			repository: $repository,
			logger: $logger
		)->cancelDonation( new CancelDonationRequest( $donation->getId(), "coolAdmin" ) );

		$this->assertSame(
			[ [ $donation->getId(), 'cancelled by user: coolAdmin' ] ],
			$logger->getLogCalls()
		);
	}

	public function testGivenIdOfNonCancellableDonation_nothingIsWrittenToTheLog(): void {
		$logger = new DonationEventLoggerSpy();
		$this->newCancelDonationUseCase( logger: $logger )->cancelDonation( new CancelDonationRequest( 1 ) );

		$this->assertSame( [], $logger->getLogCalls() );
	}

	public function testWhenConfirmationMailFails_mailDeliveryFailureResponseIsReturned(): void {
		$donation = $this->newCancelableDonation();
		$mailer = $this->newThrowingMailer();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase(
			repository: $repository,
			mailer: $mailer
		)->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );
		$this->assertTrue( $response->mailDeliveryFailed() );
	}

	private function newThrowingMailer(): TemplateMailerInterface {
		$mailer = $this->createMock( TemplateMailerInterface::class );

		$mailer->method( $this->anything() )->willThrowException( new \RuntimeException() );

		return $mailer;
	}

	public function testWhenGetDonationFails_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->throwOnRead();

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	public function testWhenDonationSavingFails_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );
		$repository->throwOnWrite();

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	public function testWhenAdminUserCancelsDonation_authorizerChecksIfSystemCanModifyDonation(): void {
		$donation = $this->newCancelableDonation();
		$authorizer = new SucceedingDonationAuthorizerSpy();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId(), "coolAdmin" );
		$this->newCancelDonationUseCase(
			repository: $repository,
			authorizer: $authorizer
		)->cancelDonation( $request );

		$this->assertTrue( $authorizer->hasAuthorizedAsAdmin() );
		$this->assertFalse( $authorizer->hasAuthorizedAsUser() );
	}

	public function testWhenDonorCancelsDonation_authorizerUsesFullAuthorizationCheck(): void {
		$donation = $this->newCancelableDonation();
		$authorizer = new SucceedingDonationAuthorizerSpy();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$this->newCancelDonationUseCase(
			repository: $repository,
			authorizer: $authorizer
		)->cancelDonation( $request );

		$this->assertFalse( $authorizer->hasAuthorizedAsAdmin() );
		$this->assertTrue( $authorizer->hasAuthorizedAsUser() );
	}

	public function testWhenAdminUserCancelsDonation_emailIsNotSent(): void {
		$mailer = $this->createMock( TemplateMailerInterface::class );

		$mailer->expects( $this->never() )->method( 'sendMail' );

		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );
		$request = new CancelDonationRequest( $donation->getId(), "coolAdmin" );
		$this->newCancelDonationUseCase(
			repository: $repository,
			mailer: $mailer
		)->cancelDonation( $request );
	}

	public function testCanceledDonationIsPersisted(): void {
		$donation = $this->newCancelableDonation();
		$repository = new DonationRepositorySpy( $donation );

		$request = new CancelDonationRequest( $donation->getId(), "coolAdmin" );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );
		$storeCalls = $repository->getStoreDonationCalls();
		$this->assertCount( 1, $storeCalls );
		$this->assertSame( 1, $storeCalls[0]->getId() );
	}

}
