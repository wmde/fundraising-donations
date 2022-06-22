<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\CreditCardPaymentNotification;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidCreditCardNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\DonationContext\Tests\Integration\DonationEventLoggerAsserter;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationResponse;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Infrastructure\FakeCreditCardService;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationUseCase
 *
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CreditCardNotificationUseCaseTest extends TestCase {

	use DonationEventLoggerAsserter;

	/** @var DoctrineDonationRepository|FakeDonationRepository|DonationRepositorySpy */
	private $repository;
	private $authorizer;
	/** @var DonationNotifier&MockObject */
	private $mailer;
	private $eventLogger;
	private $creditCardService;

	public function setUp(): void {
		$this->repository = new FakeDonationRepository();
		$this->authorizer = new SucceedingDonationAuthorizer();
		$this->mailer = $this->newMailer();
		$this->eventLogger = $this->newEventLogger();
		$this->creditCardService = new FakeCreditCardService();
	}

	public function testWhenRepositoryThrowsException_handlerReturnsFailure(): void {
		$this->repository = new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) );
		$this->authorizer = new FailingDonationAuthorizer();
		$useCase = $this->newCreditCardNotificationUseCase();
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertSame( CreditCardNotificationResponse::DATABASE_ERROR, $response->getErrorMessage() );
		$this->assertNotNull( $response->getLowLevelError() );
	}

	public function testWhenAuthorizationFails_handlerReturnsFailure(): void {
		$this->authorizer = new FailingDonationAuthorizer();
		$this->repository->storeDonation( ValidDonation::newIncompleteCreditCardDonation() );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertStringContainsString( CreditCardNotificationResponse::AUTHORIZATION_FAILED, $response->getErrorMessage() );
		$this->assertNull( $response->getLowLevelError() );
	}

	public function testWhenAuthorizationSucceeds_handlerReturnsSuccess(): void {
		$this->repository->storeDonation( ValidDonation::newIncompleteCreditCardDonation() );
		$useCase = $this->newCreditCardNotificationUseCase();
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertTrue( $response->isSuccessful() );
	}

	public function testWhenPaymentTypeIsIncorrect_handlerReturnsFailure(): void {
		$this->repository->storeDonation( ValidDonation::newDirectDebitDonation() );
		$useCase = $this->newCreditCardNotificationUseCase();
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertStringContainsString( CreditCardNotificationResponse::PAYMENT_TYPE_MISMATCH, $response->getErrorMessage() );
		$this->assertNull( $response->getLowLevelError(), 'Payment verification does not create an exception' );
	}

	public function testWhenAuthorizationSucceeds_confirmationMailIsSent(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$this->repository->storeDonation( $donation );
		$this->mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $donation );
		$useCase = $this->newCreditCardNotificationUseCase();
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertNull( $response->getLowLevelError() );
	}

	public function testWhenAuthorizationSucceeds_donationIsStored(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$this->repository = new DonationRepositorySpy( $donation );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$useCase->handleNotification( $request );
		$this->assertCount( 1, $this->repository->getStoreDonationCalls() );
	}

	public function testWhenAuthorizationSucceeds_donationIsBooked(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$this->repository = new DonationRepositorySpy( $donation );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$useCase->handleNotification( $request );
		$this->assertTrue( $this->repository->getDonationById( $donation->getId() )->isBooked() );
	}

	public function testWhenAuthorizationSucceeds_bookingEventIsLogged(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$this->repository = new DonationRepositorySpy( $donation );
		$this->eventLogger = new DonationEventLoggerSpy();

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$useCase->handleNotification( $request );

		$this->assertEventLogContainsExpression( $this->eventLogger, $donation->getId(), '/booked/' );
	}

	/**
	 * @return DonationNotifier&MockObject
	 */
	private function newMailer(): DonationNotifier {
		return $this->getMockBuilder( DonationNotifier::class )->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return DonationEventLogger^MockObject
	 */
	private function newEventLogger(): DonationEventLogger {
		return $this->createMock( DonationEventLogger::class );
	}

	private function newCreditCardNotificationUseCase(): CreditCardNotificationUseCase {
		return new CreditCardNotificationUseCase(
			$this->repository,
			$this->authorizer,
			$this->creditCardService,
			$this->mailer,
			$this->eventLogger
		);
	}

	public function testWhenPaymentAmountMismatches_handlerReturnsFailure(): void {
		$this->repository->storeDonation( ValidDonation::newIncompleteCreditCardDonation() );
		$useCase = $this->newCreditCardNotificationUseCase();
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$request->setAmount( Euro::newFromInt( 35505 ) );

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertStringContainsString( CreditCardNotificationResponse::AMOUNT_MISMATCH, $response->getErrorMessage() );
		$this->assertNull( $response->getLowLevelError(), 'Amount verification does not create an exception' );
	}

}
