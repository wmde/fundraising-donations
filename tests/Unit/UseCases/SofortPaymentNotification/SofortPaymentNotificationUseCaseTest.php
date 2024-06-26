<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\SofortPaymentNotification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Services\PaymentBookingService;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidSofortNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\StaticDonationIdRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\UseCases\BookDonationUseCase\BookDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;
use WMDE\Fundraising\DonationContext\UseCases\SofortPaymentNotification\SofortPaymentNotificationUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\SuccessResponse;

#[CoversClass( BookDonationUseCase::class )]
#[CoversClass( SofortPaymentNotificationUseCase::class )]
#[CoversClass( NotificationRequest::class )]
#[CoversClass( NotificationResponse::class )]
class SofortPaymentNotificationUseCaseTest extends TestCase {

	private function getMailer(): DonationNotifier&MockObject {
		return $this->createMock( DonationNotifier::class );
	}

	public function testWhenNotificationIsForNonExistingDonation_failureResponseIsReturned(): void {
		$useCase = new SofortPaymentNotificationUseCase(
			new StaticDonationIdRepository(),
			new FakeDonationRepository(),
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->createStub( PaymentBookingService::class ),
			$this->createEventLoggerStub()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment( 4711 );
		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertTrue( $response->hasErrors() );
	}

	public function testWhenAuthorizationFails_unhandledResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newIncompleteSofortDonation() );

		$useCase = new SofortPaymentNotificationUseCase(
			new StaticDonationIdRepository(),
			$fakeRepository,
			new FailingDonationAuthorizer(),
			$this->getMailer(),
			$this->createStub( PaymentBookingService::class ),
			$this->createEventLoggerStub()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();
		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_successResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newIncompleteSofortDonation() );
		$paymentBookingServiceStub = $this->getSucceedingPaymentBookingServiceStub();

		$useCase = new SofortPaymentNotificationUseCase(
			new StaticDonationIdRepository(),
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$paymentBookingServiceStub,
			$this->createEventLoggerStub()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();
		$response = $useCase->handleNotification( $request );

		$this->assertTrue( $response->notificationWasHandled() );
		$this->assertFalse( $response->hasErrors() );
	}

	public function testWhenAuthorizationSucceeds_donationIsStored(): void {
		$repositorySpy = new DonationRepositorySpy( ValidDonation::newIncompleteSofortDonation() );
		$paymentBookingServiceStub = $this->getSucceedingPaymentBookingServiceStub();

		$useCase = new SofortPaymentNotificationUseCase(
			new StaticDonationIdRepository(),
			$repositorySpy,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$paymentBookingServiceStub,
			$this->createEventLoggerStub()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
		$this->assertCount( 1, $repositorySpy->getStoreDonationCalls() );
	}

	public function testWhenAuthorizationSucceeds_paymentIsBooked(): void {
		$donation = ValidDonation::newIncompleteSofortDonation();
		$repository = new FakeDonationRepository( $donation );
		$request = ValidSofortNotificationRequest::newInstantPayment();

		$paymentBookingServiceMock = $this->createMock( PaymentBookingService::class );
		$paymentBookingServiceMock
			->expects( $this->once() )
			->method( 'bookPayment' )
			->with( $donation->getPaymentId(), $request->bookingData )
			->willReturn( new SuccessResponse() );

		$useCase = new SofortPaymentNotificationUseCase(
			new StaticDonationIdRepository(),
			$repository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$paymentBookingServiceMock,
			$this->createEventLoggerStub()
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenPaymentServiceReturnsFailure_unhandledResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newDirectDebitDonation() );
		$paymentBookingServiceStub = $this->createStub( PaymentBookingService::class );
		$errorMessage = 'Could not book payment - server is tired';
		$paymentBookingServiceStub->method( 'bookPayment' )->willReturn( new FailureResponse( $errorMessage ) );

		$useCase = new SofortPaymentNotificationUseCase(
			new StaticDonationIdRepository(),
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$paymentBookingServiceStub,
			$this->createEventLoggerStub()
		);
		$request = ValidSofortNotificationRequest::newInstantPayment();

		$response = $useCase->handleNotification( $request );

		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertSame( $errorMessage, $response->getMessage() );
	}

	public function testWhenAuthorizationSucceeds_confirmationMailIsSent(): void {
		$donation = ValidDonation::newIncompleteSofortDonation();
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );
		$paymentBookingServiceStub = $this->getSucceedingPaymentBookingServiceStub();

		$mailer = $this->getMailer();
		$mailer
			->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $donation );

		$useCase = new SofortPaymentNotificationUseCase(
			new StaticDonationIdRepository(),
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$mailer,
			$paymentBookingServiceStub,
			$this->createEventLoggerStub()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment( 1 );
		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	private function getSucceedingPaymentBookingServiceStub(): PaymentBookingService&Stub {
		$paymentBookingServiceStub = $this->createStub( PaymentBookingService::class );
		$paymentBookingServiceStub->method( 'bookPayment' )->willReturn( new SuccessResponse() );
		return $paymentBookingServiceStub;
	}

	private function createEventLoggerStub(): DonationEventLogger {
		return $this->createStub( DonationEventLogger::class );
	}

}
