<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\HandlePaypalPaymentWithoutDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Services\PaypalBookingService;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayPalNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\HandlePaypalPaymentWithoutDonation\HandlePaypalPaymentWithoutDonationUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CreateBookedPayPalPayment\SuccessResponse;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\HandlePaypalPaymentWithoutDonation\HandlePaypalPaymentWithoutDonationUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\NotificationResponse
 */
class HandlePaypalPaymentWithoutDonationUseCaseTest extends TestCase {

	private const PAYMENT_AMOUNT = 3465;

	public function testWhenPaymentIsForNonExistingDonation_newDonationIsCreated(): void {
		$bookingData = ValidPayPalNotificationRequest::newInstantPayment( 0 )->bookingData;
		$repositorySpy = new DonationRepositorySpy();
		$loggerSpy = new DonationEventLoggerSpy();
		$useCase = new HandlePaypalPaymentWithoutDonationUseCase(
			$this->createPaymentService( $bookingData ),
			$repositorySpy,
			$this->createNotifierExpectingNotification(),
			$loggerSpy
		);

		$result = $useCase->handleNotification( self::PAYMENT_AMOUNT, $bookingData );

		$storeDonationCalls = $repositorySpy->getStoreDonationCalls();
		$donation = $storeDonationCalls[0];
		$logs = $loggerSpy->getLogCalls();

		$this->assertCount( 1, $storeDonationCalls, 'Donation is stored' );
		$this->assertNull( $donation->getId(), 'ID is not taken from request' );

		$this->assertSame( 1, $donation->getPaymentId() );
		$this->assertTrue( $donation->donorIsAnonymous() );
		$this->assertFalse( $result->hasErrors() );
		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( 'booked', $logs[0][1] );
	}

	public function testWhenPayPalBookingServiceFails_ReturnFailureResult(): void {
		$paymentService = $this->createStub( PaypalBookingService::class );
		$paymentService
			->method( 'bookNewPayment' )
			->willReturn( new FailureResponse( "Failing for whatever reason" ) );
		$repositorySpy = new DonationRepositorySpy();
		$notifier = $this->createNotifierExpectingNoNotification();
		$loggerSpy = new DonationEventLoggerSpy();

		$useCase = new HandlePaypalPaymentWithoutDonationUseCase(
			$paymentService,
			$repositorySpy,
			$notifier,
			$loggerSpy
		);

		$result = $useCase->handleNotification( self::PAYMENT_AMOUNT, [] );

		$this->assertTrue( $repositorySpy->noDonationsStored() );
		$this->assertTrue( $result->hasErrors() );
		$this->assertCount( 0, $loggerSpy->getLogCalls() );
	}

	private function createPaymentService( array $bookingData ): PaypalBookingService {
		$paymentService = $this->createMock( PaypalBookingService::class );
		$paymentService->expects( $this->once() )
			->method( 'bookNewPayment' )
			->with( self::PAYMENT_AMOUNT, $bookingData )
			->willReturn( new SuccessResponse( 1 ) );
		return $paymentService;
	}

	private function createNotifierExpectingNotification(): DonationNotifier {
		$notifier = $this->createMock( DonationNotifier::class );
		$notifier->expects( $this->once() )->method( 'sendConfirmationFor' );
		return $notifier;
	}

	private function createNotifierExpectingNoNotification(): DonationNotifier {
		$notifier = $this->createMock( DonationNotifier::class );
		$notifier->expects( $this->never() )->method( 'sendConfirmationFor' );
		return $notifier;
	}
}
