<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\CreditCardPaymentNotification;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;
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
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardPaymentHandlerException;
use WMDE\Fundraising\PaymentContext\Infrastructure\FakeCreditCardService;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationUseCase
 *
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CreditCardNotificationUseCaseTest extends TestCase {

	use DonationEventLoggerAsserter;

	/** @var DoctrineDonationRepository|FakeDonationRepository|DonationRepositorySpy */
	private $repository;
	private $authorizer;
	/** @var DonationConfirmationMailer|\PHPUnit_Framework_MockObject_MockObject */
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

	public function testWhenRepositoryThrowsException_handlerThrowsException(): void {
		$this->repository = new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) );
		$this->authorizer = new FailingDonationAuthorizer();
		$useCase = $this->newCreditCardNotificationUseCase();
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$this->expectException( CreditCardPaymentHandlerException::class );
		$useCase->handleNotification( $request );
	}

	public function testWhenAuthorizationFails_handlerThrowsException(): void {
		$this->authorizer = new FailingDonationAuthorizer();
		$this->repository->storeDonation( ValidDonation::newIncompleteCreditCardDonation() );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$this->expectException( CreditCardPaymentHandlerException::class );
		$useCase->handleNotification( $request );
	}

	public function testWhenAuthorizationSucceeds_handlerDoesNotThrowException(): void {
		$this->repository->storeDonation( ValidDonation::newIncompleteCreditCardDonation() );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		try {
			$useCase->handleNotification( $request );
			$this->assertTrue( true );
		}
		catch ( \Exception $e ) {
			$this->fail();
		}
	}

	public function testWhenPaymentTypeIsIncorrect_handlerThrowsException(): void {
		$this->repository->storeDonation( ValidDonation::newDirectDebitDonation() );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$this->expectException( CreditCardPaymentHandlerException::class );
		$useCase->handleNotification( $request );
	}

	public function testWhenAuthorizationSucceeds_confirmationMailIsSent(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$this->repository->storeDonation( $donation );

		$this->mailer->expects( $this->once() )
			->method( 'sendConfirmationMailFor' )
			->with( $donation );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$useCase->handleNotification( $request );
	}

	public function testWhenAuthorizationSucceedsForAnonymousDonation_confirmationMailIsNotSent(): void {
		$donation = ValidDonation::newIncompleteAnonymousCreditCardDonation();
		$this->repository->storeDonation( $donation );

		$this->mailer->expects( $this->never() )
			->method( 'sendConfirmationMailFor' );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$useCase->handleNotification( $request );
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

	public function testWhenSendingConfirmationMailFails_handlerDoesNotThrowException(): void {
		$this->repository->storeDonation( ValidDonation::newIncompleteCreditCardDonation() );

		$this->mailer->expects( $this->once() )
			->method( 'sendConfirmationMailFor' )
			->willThrowException( new \RuntimeException( 'Oh noes!' ) );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		try {
			$useCase->handleNotification( $request );
		}
		catch ( \Exception $e ) {
			$this->fail();
		}
	}

	/**
	 * @return DonationConfirmationMailer|\PHPUnit_Framework_MockObject_MockObject
	 */
	private function newMailer(): DonationConfirmationMailer {
		return $this->getMockBuilder( DonationConfirmationMailer::class )->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return DonationEventLogger|\PHPUnit_Framework_MockObject_MockObject
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
			new NullLogger(),
			$this->eventLogger
		);
	}

	public function testWhenPaymentAmountMismatches_handlerThreepwoodsException(): void {
		$this->repository->storeDonation( ValidDonation::newIncompleteCreditCardDonation() );

		$useCase = $this->newCreditCardNotificationUseCase();

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$request->setAmount( Euro::newFromInt( 35505 ) );

		$this->expectException( CreditCardPaymentHandlerException::class );
		$useCase->handleNotification( $request );
	}

}
