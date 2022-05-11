<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\CancelDonation;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\TemplateMailerInterface;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationResponse;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\CancelPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\SuccessResponse;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationResponse
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest
 *
 * @license GPL-2.0-or-later
 */
class CancelDonationUseCaseTest extends TestCase {

	/**
	 * @var DonationRepository|FakeDonationRepository|DonationRepositorySpy
	 */
	private DonationRepository $repository;

	/**
	 * @var TemplateMailerInterface|TemplateBasedMailerSpy
	 */
	private $mailer;

	/**
	 * @var DonationAuthorizer|SucceedingDonationAuthorizerSpy
	 */
	private DonationAuthorizer $authorizer;

	private DonationEventLoggerSpy $logger;

	public function setUp(): void {
		$this->repository = new FakeDonationRepository();
		$this->mailer = new TemplateBasedMailerSpy( $this );
		$this->authorizer = new SucceedingDonationAuthorizerSpy();
		$this->logger = new DonationEventLoggerSpy();
	}

	private function newCancelDonationUseCase(): CancelDonationUseCase {
		return new CancelDonationUseCase(
			$this->repository,
			$this->mailer,
			$this->authorizer,
			$this->logger,
			$this->getSucceedingCancelPaymentUseCase()
		);
	}

	private function newCancelDonationUseCasePaymentCancellationFails(): CancelDonationUseCase {
		return new CancelDonationUseCase(
			$this->repository,
			$this->mailer,
			$this->authorizer,
			$this->logger,
			$this->getFailingCancelPaymentUseCase()
		);
	}

	private function getSucceedingCancelPaymentUseCase(): CancelPaymentUseCase|MockObject {
		$cancelPaymentUseCase = $this->createMock( CancelPaymentUseCase::class );
		$cancelPaymentUseCase->method( 'cancelPayment' )->willReturn( new SuccessResponse() );
		return $cancelPaymentUseCase;
	}

	private function getFailingCancelPaymentUseCase(): CancelPaymentUseCase|MockObject {
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
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );
		$this->assertFalse( $response->mailDeliveryFailed() );
		$this->assertTrue( $this->repository->getDonationById( $donation->getId() )->isCancelled() );
	}

	public function testGivenIdOfNonCancellableDonation_cancellationIsNotSuccessful(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCasePaymentCancellationFails()->cancelDonation( $request );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	private function newCancelableDonation(): Donation {
		// direct debit and bank transfer are cancelable payment types
		return ValidDonation::newDirectDebitDonation();
	}

	public function testWhenDonationGetsCancelled_cancellationConfirmationEmailIsSent(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );
		$this->mailer->assertCalledOnceWith(
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
		$this->repository->storeDonation( $donation );

		$this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( $donation->getId() ) );

		$this->assertSame(
			[ [ $donation->getId(), 'frontend: storno' ] ],
			$this->logger->getLogCalls()
		);
	}

	public function testWhenDonationGetsCancelledByAdmin_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( $donation->getId(), "coolAdmin" ) );

		$this->assertSame(
			[ [ $donation->getId(), 'cancelled by user: coolAdmin' ] ],
			$this->logger->getLogCalls()
		);
	}

	public function testGivenIdOfNonCancellableDonation_nothingIsWrittenToTheLog(): void {
		$this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1 ) );

		$this->assertSame( [], $this->logger->getLogCalls() );
	}

	public function testWhenConfirmationMailFails_mailDeliveryFailureResponseIsReturned(): void {
		$this->mailer = $this->newThrowingMailer();

		$response = $this->getResponseForCancellableDonation();

		$this->assertTrue( $response->cancellationSucceeded() );
		$this->assertTrue( $response->mailDeliveryFailed() );
	}

	private function newThrowingMailer(): TemplateMailerInterface {
		$mailer = $this->createMock( TemplateMailerInterface::class );

		$mailer->method( $this->anything() )->willThrowException( new \RuntimeException() );

		return $mailer;
	}

	public function testWhenGetDonationFails_cancellationIsNotSuccessful(): void {
		$this->repository->throwOnRead();

		$response = $this->getResponseForCancellableDonation();

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	private function getResponseForCancellableDonation(): CancelDonationResponse {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		return $this->newCancelDonationUseCase()->cancelDonation( $request );
	}

	public function testWhenDonationSavingFails_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );
		$this->repository->throwOnWrite();

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	public function testWhenAdminUserCancelsDonation_authorizerChecksIfSystemCanModifyDonation(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId(), "coolAdmin" );
		$this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertTrue( $this->authorizer->hasAuthorizedAsAdmin() );
		$this->assertFalse( $this->authorizer->hasAuthorizedAsUser() );
	}

	public function testWhenDonorCancelsDonation_authorizerUsesFullAuthorizationCheck(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertFalse( $this->authorizer->hasAuthorizedAsAdmin() );
		$this->assertTrue( $this->authorizer->hasAuthorizedAsUser() );
	}

	public function testWhenAdminUserCancelsDonation_emailIsNotSent(): void {
		$this->mailer = $this->createMock( TemplateMailerInterface::class );
		$this->mailer->expects( $this->never() )->method( 'sendMail' );

		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId(), "coolAdmin" );
		$this->newCancelDonationUseCase()->cancelDonation( $request );
	}

	public function testCanceledDonationIsPersisted(): void {
		$donation = $this->newCancelableDonation();
		$this->repository = new DonationRepositorySpy( $donation );

		$request = new CancelDonationRequest( $donation->getId(), "coolAdmin" );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );
		$storeCalls = $this->repository->getStoreDonationCalls();
		$this->assertCount( 1, $storeCalls );
		$this->assertSame( $donation->getId(), $storeCalls[0]->getId() );
	}

}
