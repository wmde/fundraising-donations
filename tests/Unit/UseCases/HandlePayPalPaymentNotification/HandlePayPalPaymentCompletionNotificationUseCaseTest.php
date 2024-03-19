<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\HandlePayPalPaymentNotification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Services\PaymentBookingService;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayPalNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\StaticDonationIdRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Integration\DonationEventLoggerAsserter;
use WMDE\Fundraising\DonationContext\UseCases\BookDonationUseCase\BookDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\HandlePayPalPaymentNotification\HandlePayPalPaymentCompletionNotificationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FollowUpSuccessResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\SuccessResponse;

#[CoversClass( HandlePayPalPaymentCompletionNotificationUseCase::class )]
#[CoversClass( BookDonationUseCase::class )]
#[CoversClass( NotificationResponse::class )]
class HandlePayPalPaymentCompletionNotificationUseCaseTest extends TestCase {

	use DonationEventLoggerAsserter;

	public function testWhenAuthorizationFails_failureResponseIsReturned(): void {
		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$useCase = $this->givenNewUseCase(
			authorizer: new FailingDonationAuthorizer()
		);

		$this->assertFalse( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_successResponseIsReturned(): void {
		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$useCase = $this->givenNewUseCase();

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_confirmationMailIsSent(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();

		$notifier = $this->createMock( DonationNotifier::class );
		$notifier->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $donation );

		$fakeRepository = new FakeDonationRepository( $donation );
		$useCase = $this->givenNewUseCase(
			repository: $fakeRepository,
			notifier: $notifier
		);

		$request = ValidPayPalNotificationRequest::newInstantPayment( $donation->getId() );
		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_donationIsStored(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$repositorySpy = new DonationRepositorySpy( $donation );

		$request = ValidPayPalNotificationRequest::newInstantPayment( $donation->getId() );
		$useCase = $this->givenNewUseCase(
			repository: $repositorySpy,
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
		$this->assertCount( 1, $repositorySpy->getStoreDonationCalls() );
	}

	public function testWhenAuthorizationSucceeds_paymentIsBooked(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$repository = new FakeDonationRepository( $donation );
		$request = ValidPayPalNotificationRequest::newInstantPayment( $donation->getId() );

		$paymentBookingServiceMock = $this->createMock( PaymentBookingService::class );
		$paymentBookingServiceMock
			->expects( $this->once() )
			->method( 'bookPayment' )
			->with( $donation->getPaymentId(), $request->bookingData )
			->willReturn( new SuccessResponse() );

		$useCase = $this->givenNewUseCase(
			repository: $repository,
			paymentBookingService: $paymentBookingServiceMock
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_bookingEventIsLogged(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$repository = new FakeDonationRepository( $donation );
		$eventLogger = new DonationEventLoggerSpy();

		$request = ValidPayPalNotificationRequest::newInstantPayment( $donation->getId() );
		$useCase = $this->givenNewUseCase(
			repository: $repository,
			eventLogger: $eventLogger
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );

		$this->assertCount( 1, $eventLogger->getLogCalls() );
		$this->assertEventLogContainsExpression( $eventLogger, $donation->getId(), '/booked/' );
	}

	public function testGivenNewTransactionIdForBookedDonation_createsNewDonation(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$transactionId = '16R12136PU8783961';

		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );

		$request = ValidPayPalNotificationRequest::newDuplicatePayment( $donation->getId(), $transactionId );

		$useCase = $this->givenNewUseCase(
			repository: $fakeRepository,
			paymentBookingService: $this->createFollowUpSucceedingPaymentBookingServiceStub(),
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );

		$repositoryCalls = $fakeRepository->getDonations();

		$this->assertCount( 2, $repositoryCalls );

		[ $donation, $followupUpDonation ] = array_values( $repositoryCalls );
		$this->assertNotSame( $followupUpDonation->getId(), $donation->getId() );
		$this->assertEquals( $followupUpDonation->getDonor(), $donation->getDonor() );
		$this->assertEquals( $followupUpDonation->getTrackingInfo(), $donation->getTrackingInfo() );
		$this->assertEquals( $followupUpDonation->getOptsIntoNewsletter(), $donation->getOptsIntoNewsletter() );
		$this->assertFalse( $followupUpDonation->isExported() );
	}

	public function testGivenNewTransactionIdForBookedDonation_childCreationEventIsLogged(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$transactionId = '16R12136PU8783961';
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );

		$eventLogger = new DonationEventLoggerSpy();
		$useCase = $this->givenNewUseCase(
			repository: $fakeRepository,
			paymentBookingService: $this->createFollowUpSucceedingPaymentBookingServiceStub(),
			eventLogger: $eventLogger
		);

		$request = ValidPayPalNotificationRequest::newDuplicatePayment( $donation->getId(), $transactionId );

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );

		$repositoryCalls = $fakeRepository->getDonations();

		$this->assertCount( 2, $repositoryCalls );

		[ $donation, $followupUpDonation ] = array_values( $repositoryCalls );

		$this->assertCount(
			3,
			$eventLogger->getLogCalls(),
			'booking of the new donation and linking of parent and child donations should be logged'
		);
		$this->assertEventLogContainsExpression(
			$eventLogger,
			1,
			'/child donation.*' . $followupUpDonation->getId() . '/'
		);
		$this->assertEventLogContainsExpression(
			$eventLogger,
			intval( $followupUpDonation->getId() ),
			'/parent donation.*' . $donation->getId() . '/'
		);
	}

	public function testGivenNewTransactionIdForBookedDonation_noConfirmationMailIsSent(): void {
		$notifier = $this->createMock( DonationNotifier::class );
		$notifier->expects( $this->never() )
			->method( 'sendConfirmationFor' );

		$useCase = $this->givenNewUseCase(
			notifier: $notifier,
			paymentBookingService: $this->createFollowUpSucceedingPaymentBookingServiceStub(),
		);

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testFailingPaymentBookingService_notificationIsNotHandled(): void {
		$errorMessage = 'Could not book payment - server is tired';
		$failingPaymentService = $this->createStub( PaymentBookingService::class );
		$failingPaymentService->method( 'bookPayment' )->willReturn( new FailureResponse( $errorMessage ) );

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );

		$useCase = $this->givenNewUseCase(
			paymentBookingService: $failingPaymentService
		);

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertSame( $errorMessage, $response->getMessage(), 'Response should contain message from payment service' );
	}

	public function testWhenDonationDoesNotExist_returnsDonationWasNotFoundResponse(): void {
		$repository = $this->createMock( DonationRepository::class );
		$repository->method( 'getDonationById' )->willReturn( null );

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );

		$useCase = $this->givenNewUseCase( repository: $repository );

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertTrue( $response->donationWasNotFound() );
	}

	public function testWhenPaymentIsAlreadyBooked_returnsPaymentAlreadyBookedResponse(): void {
		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$bookPaymentResponse = FailureResponse::newAlreadyCompletedResponse();
		$useCase = $this->givenNewUseCase(
			paymentBookingService: $this->createFailingPaymentBookingServiceStub( $bookPaymentResponse )
		);

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertTrue( $response->paymentWasAlreadyCompleted() );
	}

	private function givenNewUseCase(
		?DonationIdRepository $idGenerator = null,
		?DonationRepository $repository = null,
		?DonationAuthorizationChecker $authorizer = null,
		?DonationNotifier $notifier = null,
		?PaymentBookingService $paymentBookingService = null,
		?DonationEventLogger $eventLogger = null,
	): HandlePayPalPaymentCompletionNotificationUseCase {
		return new HandlePayPalPaymentCompletionNotificationUseCase(
			$idGenerator ?? new StaticDonationIdRepository(),
			$repository ?? $this->createFakeRepository(),
			authorizationService: $authorizer ?? new SucceedingDonationAuthorizer(),
			notifier: $notifier ?? $this->createNotifierStub(),
			paymentBookingService: $paymentBookingService ?? $this->createSucceedingPaymentBookingServiceStub(),
			eventLogger: $eventLogger ?? $this->createEventLoggerStub()
		);
	}

	private function createNotifierStub(): DonationNotifier {
		return $this->createStub( DonationNotifier::class );
	}

	private function createEventLoggerStub(): DonationEventLogger {
		return $this->createStub( DonationEventLogger::class );
	}

	private function createSucceedingPaymentBookingServiceStub(): PaymentBookingService&Stub {
		$paymentBookingServiceStub = $this->createStub( PaymentBookingService::class );
		$paymentBookingServiceStub->method( 'bookPayment' )->willReturn( new SuccessResponse() );
		return $paymentBookingServiceStub;
	}

	private function createFailingPaymentBookingServiceStub( FailureResponse $response ): PaymentBookingService&Stub {
		$paymentBookingServiceStub = $this->createStub( PaymentBookingService::class );
		$paymentBookingServiceStub->method( 'bookPayment' )->willReturn( $response );
		return $paymentBookingServiceStub;
	}

	private function createFollowUpSucceedingPaymentBookingServiceStub(): PaymentBookingService&Stub {
		$paymentBookingServiceStub = $this->createStub( PaymentBookingService::class );
		$paymentBookingServiceStub->method( 'bookPayment' )->willReturn( new FollowUpSuccessResponse( 1, 2 ) );
		return $paymentBookingServiceStub;
	}

	private function createFakeRepository(): DonationRepository {
		return new FakeDonationRepository( ValidDonation::newIncompletePayPalDonation() );
	}

}
