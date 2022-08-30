<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\CreditCardPaymentNotification;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Services\PaymentBookingService;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidCreditCardNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Integration\DonationEventLoggerAsserter;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\SuccessResponse;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardNotificationUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\NotificationResponse
 *
 */
class CreditCardNotificationUseCaseTest extends TestCase {

	use DonationEventLoggerAsserter;

	public function testWhenAuthorizationFails_handlerReturnsFailure(): void {
		$authorizer = new FailingDonationAuthorizer();
		$repository = new FakeDonationRepository( ValidDonation::newIncompleteCreditCardDonation() );
		$useCase = $this->newCreditCardNotificationUseCase(
			donationRepository: $repository,
			authorizer: $authorizer
		);

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertTrue( $response->hasErrors() );
		$this->assertSame( 'Wrong access code for donation', $response->getMessage() );
	}

	public function testWhenAuthorizationSucceeds_handlerReturnsSuccess(): void {
		$repository = new FakeDonationRepository( ValidDonation::newIncompleteCreditCardDonation() );
		$useCase = $this->newCreditCardNotificationUseCase(
			donationRepository: $repository,
		);
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertTrue( $response->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_confirmationMailIsSent(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$repository = new FakeDonationRepository( $donation );

		$mailer = $this->createMock( DonationNotifier::class );
		$mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $donation );
		$useCase = $this->newCreditCardNotificationUseCase(
			donationRepository: $repository,
			notifier: $mailer
		);
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$useCase->handleNotification( $request );
	}

	public function testWhenAuthorizationSucceeds_donationIsStored(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$repository = new DonationRepositorySpy( $donation );

		$useCase = $this->newCreditCardNotificationUseCase(
			donationRepository: $repository,
		);

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$useCase->handleNotification( $request );
		$this->assertCount( 1, $repository->getStoreDonationCalls() );
	}

	public function testWhenAuthorizationSucceeds_paymentIsBooked(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$repository = new FakeDonationRepository( $donation );

		$paymentBookingServiceMock = $this->createMock( PaymentBookingService::class );
		$paymentBookingServiceMock
			->expects( $this->once() )
			->method( 'bookPayment' )
			->with( $donation->getPaymentId(), $request->bookingData )
			->willReturn( new SuccessResponse() );

		$useCase = $this->newCreditCardNotificationUseCase(
			donationRepository: $repository,
			paymentBookingService: $paymentBookingServiceMock,
		);
		$useCase->handleNotification( $request );
	}

	public function testWhenAuthorizationSucceeds_bookingEventIsLogged(): void {
		$donation = ValidDonation::newIncompleteCreditCardDonation();
		$repository = new DonationRepositorySpy( $donation );
		$eventLogger = new DonationEventLoggerSpy();

		$useCase = $this->newCreditCardNotificationUseCase(
			donationRepository: $repository,
			eventLogger: $eventLogger
		);

		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );
		$useCase->handleNotification( $request );

		$this->assertEventLogContainsExpression( $eventLogger, $donation->getId(), '/booked/' );
	}

	public function testWhenPaymentBookingServiceFails_handlerReturnsFailure(): void {
		$repository = new FakeDonationRepository( ValidDonation::newIncompleteCreditCardDonation() );
		$paymentService = $this->createFailingPaymentBookingService();
		$useCase = $this->newCreditCardNotificationUseCase(
			donationRepository: $repository,
			paymentBookingService: $paymentService
		);
		$request = ValidCreditCardNotificationRequest::newBillingNotification( 1 );

		$response = $useCase->handleNotification( $request );

		$this->assertTrue( $response->hasErrors() );
		$this->assertSame( 'Amount does not match', $response->getMessage() );
	}

	private function newCreditCardNotificationUseCase(
		?DonationRepository $donationRepository = null,
		?DonationAuthorizer $authorizer = null,
		?DonationNotifier $notifier = null,
		?PaymentBookingService $paymentBookingService = null,
		?DonationEventLogger $eventLogger = null

	): CreditCardNotificationUseCase {
		return new CreditCardNotificationUseCase(
			$donationRepository ?? new FakeDonationRepository(),
			$authorizer ?? new SucceedingDonationAuthorizer(),
			$notifier ?? $this->createNotifierStub(),
			$paymentBookingService ?? $this->createSucceedingPaymentBookingService(),
			$eventLogger ?? $this->createEventLoggerStub()
		);
	}

	private function createNotifierStub(): DonationNotifier {
		return $this->createStub( DonationNotifier::class );
	}

	private function createSucceedingPaymentBookingService(): PaymentBookingService {
		$service = $this->createStub( PaymentBookingService::class );
		$service->method( 'bookPayment' )->willReturn( new SuccessResponse() );
		return $service;
	}

	private function createEventLoggerStub(): DonationEventLogger {
		return $this->createStub( DonationEventLogger::class );
	}

	private function createFailingPaymentBookingService(): PaymentBookingService {
		$service = $this->createStub( PaymentBookingService::class );
		$service->method( 'bookPayment' )->willReturn( new FailureResponse( 'Amount does not match' ) );
		return $service;
	}

}
