<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\RestoreDonation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\UseCases\RestoreDonation\RestoreDonationFailureResponse;
use WMDE\Fundraising\DonationContext\UseCases\RestoreDonation\RestoreDonationSuccessResponse;
use WMDE\Fundraising\DonationContext\UseCases\RestoreDonation\RestoreDonationUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\CancelPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\SuccessResponse;

#[CoversClass( RestoreDonationUseCase::class )]
#[CoversClass( RestoreDonationSuccessResponse::class )]
#[CoversClass( RestoreDonationFailureResponse::class )]
class RestoreDonationUseCaseTest extends TestCase {

	private const string AUTH_USER_NAME = "coolAdmin";

	public function testGivenNonExistingDonation_restoreFails(): void {
		$donationLogger = new DonationEventLoggerSpy();
		$useCase = $this->givenRestoreDonationUseCase( donationLogger: $donationLogger );

		$response = $useCase->restoreCancelledDonation( 1, self::AUTH_USER_NAME );

		$this->assertInstanceOf( RestoreDonationFailureResponse::class, $response );
		$this->assertSame( RestoreDonationFailureResponse::DONATION_NOT_FOUND, $response->message );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsNotCancelled_restoreFails(): void {
		$fakeDonationRepository = new FakeDonationRepository( ValidDonation::newBankTransferDonation() );
		$donationLogger = new DonationEventLoggerSpy();
		$useCase = $this->givenRestoreDonationUseCase(
			donationRepository: $fakeDonationRepository,
			donationLogger: $donationLogger
		);

		$response = $useCase->restoreCancelledDonation( 1, self::AUTH_USER_NAME );

		$this->assertInstanceOf( RestoreDonationFailureResponse::class, $response );
		$this->assertSame( RestoreDonationFailureResponse::DONATION_NOT_CANCELED, $response->message );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenFailingPaymentRestoration_restoreFails(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$failureMessage = 'Payment cannot be restored';
		$paymentUseCase = $this->createConfiguredStub(
			CancelPaymentUseCase::class,
			[ 'restorePayment' => new FailureResponse( $failureMessage ) ]
		);
		$useCase = $this->givenRestoreDonationUseCase( donationRepository: $fakeDonationRepository, cancelPaymentUseCase: $paymentUseCase );

		$response = $useCase->restoreCancelledDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertInstanceOf( RestoreDonationFailureResponse::class, $response );
		$this->assertSame( $failureMessage, $response->message, 'Response should contain failure message from payment use case' );
	}

	public function testGivenCancelledDonation_restoreSucceeds(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$useCase = $this->givenRestoreDonationUseCase( donationRepository: $fakeDonationRepository );

		$response = $useCase->restoreCancelledDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertInstanceOf( RestoreDonationSuccessResponse::class, $response );
		$this->assertFalse( $donation->isCancelled() );
	}

	public function testPaymentRestorationIsCalledWithPaymentId(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$paymentUseCase = $this->createMock( CancelPaymentUseCase::class );

		$paymentUseCase->expects( $this->once() )
			->method( 'restorePayment' )
			->with( $donation->getPaymentId() )
			->willReturn( new SuccessResponse( true ) );

		$useCase = $this->givenRestoreDonationUseCase( donationRepository: $fakeDonationRepository, cancelPaymentUseCase: $paymentUseCase );
		$useCase->restoreCancelledDonation( $donation->getId(), self::AUTH_USER_NAME );
	}

	public function testRestoredDonationIsPersisted(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$donationRepositorySpy = new DonationRepositorySpy( $donation );
		$useCase = $this->givenRestoreDonationUseCase( donationRepository: $donationRepositorySpy );

		$response = $useCase->restoreCancelledDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertInstanceOf( RestoreDonationSuccessResponse::class, $response );
		$storeCalls = $donationRepositorySpy->getStoreDonationCalls();
		$this->assertCount( 1, $storeCalls );
		$this->assertSame( $donation->getId(), $storeCalls[0]->getId() );
	}

	public function testWhenCancelledDonationGetsRestored_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();
		$useCase = $this->givenRestoreDonationUseCase( donationRepository: $fakeDonationRepository, donationLogger: $donationLogger );

		$useCase->restoreCancelledDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertSame(
			[ [ $donation->getId(), 'restored by user: coolAdmin' ] ],
			$donationLogger->getLogCalls()
		);
	}

	private function givenRestoreDonationUseCase(
		?DonationRepository $donationRepository = null,
		?DonationEventLogger $donationLogger = null,
		?CancelPaymentUseCase $cancelPaymentUseCase = null
	): RestoreDonationUseCase {
		return new RestoreDonationUseCase(
			$donationRepository ?? new FakeDonationRepository(),
			$donationLogger ?? new DonationEventLoggerSpy(),
			$cancelPaymentUseCase ?? $this->createSucceedingCancelPaymentUseCase()
		);
	}

	private function createSucceedingCancelPaymentUseCase(): CancelPaymentUseCase {
		$useCase = $this->createConfiguredStub(
			CancelPaymentUseCase::class,
			[ 'restorePayment' => new SuccessResponse( true ) ]
		);
		$useCase->method( 'cancelPayment' )->willThrowException( new \LogicException( 'Restore donation use case must not call cancel payment use case' ) );
		return $useCase;
	}

}
