<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\CancelDonation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizerSpy;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationFailureResponse;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationSuccessResponse;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\CancelPaymentUseCase;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CancelPayment\SuccessResponse;

#[CoversClass( CancelDonationUseCase::class )]
#[CoversClass( CancelDonationRequest::class )]
#[CoversClass( CancelDonationSuccessResponse::class )]
#[CoversClass( CancelDonationFailureResponse::class )]
class CancelDonationUseCaseTest extends TestCase {

	private const AUTHORIZED_USER = 'admin_adminson';

	private function newCancelDonationUseCase(
		?DonationRepository $repository = null,
		?DonationAuthorizationChecker $authorizer = null,
		?DonationEventLogger $logger = null,
		?CancelPaymentUseCase $cancelPaymentUseCase = null
	): CancelDonationUseCase {
		return new CancelDonationUseCase(
			$repository ?? new FakeDonationRepository(),
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
		$cancelPaymentUseCase->method( 'cancelPayment' )->willReturn( new FailureResponse( "canceling payment not allowed" ) );
		return $cancelPaymentUseCase;
	}

	public function testGivenIdOfUnknownDonation_cancellationIsNotSuccessful(): void {
		$response = $this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1, self::AUTHORIZED_USER ) );

		$this->assertInstanceOf( CancelDonationFailureResponse::class, $response );
		$this->assertSame( 'Donation not found.', $response->message );
	}

	public function testResponseContainsDonationId(): void {
		$response = $this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1337, self::AUTHORIZED_USER ) );

		$this->assertSame( 1337, $response->donationId );
	}

	public function testGivenIdOfCancellableDonation_cancellationIsSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId(), self::AUTHORIZED_USER );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );
		$donation = $repository->getDonationById( $donation->getId() );

		$this->assertNotNull( $donation );
		$this->assertTrue( $donation->isCancelled() );
		$this->assertInstanceOf( CancelDonationSuccessResponse::class, $response );
	}

	public function testGivenIdOfNonCancellableDonation_cancellationIsNotSuccessful(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId(), self::AUTHORIZED_USER );
		$response = $this->newCancelDonationUseCase(
			repository: $repository,
			cancelPaymentUseCase: $this->getFailingCancelPaymentUseCase()
		)->cancelDonation( $request );

		$this->assertInstanceOf( CancelDonationFailureResponse::class, $response );
		$this->assertSame( 'canceling payment not allowed', $response->message );
	}

	private function newCancelableDonation(): Donation {
		// direct debit and bank transfer are cancelable payment types
		return ValidDonation::newDirectDebitDonation();
	}

	public function testWhenDonationGetsCancelledByAdmin_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = $this->newCancelableDonation();
		$logger = new DonationEventLoggerSpy();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$this->newCancelDonationUseCase(
			repository: $repository,
			logger: $logger
		)->cancelDonation( new CancelDonationRequest( $donation->getId(), self::AUTHORIZED_USER ) );

		$this->assertSame(
			[ [ $donation->getId(), 'cancelled by user: admin_adminson' ] ],
			$logger->getLogCalls()
		);
	}

	public function testGivenIdOfNonCancellableDonation_nothingIsWrittenToTheLog(): void {
		$logger = new DonationEventLoggerSpy();
		$this->newCancelDonationUseCase( logger: $logger )->cancelDonation( new CancelDonationRequest( 1, self::AUTHORIZED_USER ) );

		$this->assertSame( [], $logger->getLogCalls() );
	}

	public function testWhenGetDonationFails_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->throwOnRead();

		$request = new CancelDonationRequest( $donation->getId(), self::AUTHORIZED_USER );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );

		$this->assertInstanceOf( CancelDonationFailureResponse::class, $response );
		$this->assertSame( 'Could not get donation', $response->message );
	}

	public function testWhenDonationSavingFails_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );
		$repository->throwOnWrite();

		$request = new CancelDonationRequest( $donation->getId(), self::AUTHORIZED_USER );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );

		$this->assertInstanceOf( CancelDonationFailureResponse::class, $response );
		$this->assertSame( 'Could not store donation', $response->message );
	}

	public function testWhenAdminUserCancelsDonation_authorizerChecksIfSystemCanModifyDonation(): void {
		$donation = $this->newCancelableDonation();
		$authorizer = new SucceedingDonationAuthorizerSpy();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId(), self::AUTHORIZED_USER );
		$this->newCancelDonationUseCase(
			repository: $repository,
			authorizer: $authorizer
		)->cancelDonation( $request );

		$this->assertTrue( $authorizer->hasAuthorizedAsAdmin() );
		$this->assertFalse( $authorizer->hasAuthorizedAsUser() );
	}

	public function testWhenAuthorizationFails_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$repository = new FakeDonationRepository();
		$repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId(), self::AUTHORIZED_USER );
		$response = $this->newCancelDonationUseCase(
			repository: $repository,
			authorizer: new FailingDonationAuthorizer()
		)->cancelDonation( $request );

		$this->assertInstanceOf( CancelDonationFailureResponse::class, $response );
		$this->assertSame( 'Not allowed to cancel donations.', $response->message );
	}

	public function testCanceledDonationIsPersisted(): void {
		$donation = $this->newCancelableDonation();
		$repository = new DonationRepositorySpy( $donation );

		$request = new CancelDonationRequest( $donation->getId(), "coolAdmin" );
		$response = $this->newCancelDonationUseCase( repository: $repository )->cancelDonation( $request );

		$this->assertInstanceOf( CancelDonationSuccessResponse::class, $response );
		$storeCalls = $repository->getStoreDonationCalls();
		$this->assertCount( 1, $storeCalls );
		$this->assertSame( 1, $storeCalls[0]->getId() );
	}

}
