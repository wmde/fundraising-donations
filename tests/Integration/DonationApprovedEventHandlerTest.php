<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\DonationApprovedEventHandler;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;

#[CoversClass( DonationApprovedEventHandler::class )]
class DonationApprovedEventHandlerTest extends TestCase {

	private const UNKNOWN_ID = 32202;
	private const KNOWN_ID = 31337;

	private DonationAuthorizationChecker $authorizer;
	private DonationRepository $repository;

	public function setUp(): void {
		$this->authorizer = new SucceedingDonationAuthorizer();
		$this->repository = new FakeDonationRepository( $this->newDonation() );
	}

	private function newDonation(): Donation {
		return ValidDonation::newBankTransferDonation( self::KNOWN_ID );
	}

	public function testWhenAuthorizationFails_errorIsReturned(): void {
		$this->authorizer = new FailingDonationAuthorizer();
		$eventHandler = $this->newDonationApprovedEventHandler( $this->createStub( DonationNotifier::class ) );

		$result = $eventHandler->onDonationApproved( self::UNKNOWN_ID );

		$this->assertSame( DonationApprovedEventHandler::AUTHORIZATION_FAILED, $result );
	}

	private function newDonationApprovedEventHandler( DonationNotifier $mailer ): DonationApprovedEventHandler {
		return new DonationApprovedEventHandler(
			$this->authorizer,
			$this->repository,
			$mailer
		);
	}

	public function testGivenIdOfUnknownDonation_errorIsReturned(): void {
		$eventHandler = $this->newDonationApprovedEventHandler( $this->createStub( DonationNotifier::class ) );

		$result = $eventHandler->onDonationApproved( self::UNKNOWN_ID );

		$this->assertSame( DonationApprovedEventHandler::UNKNOWN_ID_PROVIDED, $result );
	}

	public function testGivenKnownIdAndValidAuth_successIsReturned(): void {
		$eventHandler = $this->newDonationApprovedEventHandler( $this->createStub( DonationNotifier::class ) );

		$result = $eventHandler->onDonationApproved( self::KNOWN_ID );

		$this->assertSame( DonationApprovedEventHandler::SUCCESS, $result );
	}

	public function testGivenKnownIdAndValidAuth_mailerIsInvoked(): void {
		$mailer = $this->createMock( DonationNotifier::class );
		$mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $this->newDonation() );

		$this->newDonationApprovedEventHandler( $mailer )->onDonationApproved( self::KNOWN_ID );
	}

	public function testGivenIdOfUnknownDonation_mailerIsNotInvoked(): void {
		$mailer = $this->createMock( DonationNotifier::class );
		$mailer->expects( $this->never() )->method( $this->anything() );
		$this->newDonationApprovedEventHandler( $mailer )->onDonationApproved( self::UNKNOWN_ID );
	}

	public function testWhenAuthorizationFails_mailerIsNotInvoked(): void {
		$mailer = $this->createMock( DonationNotifier::class );
		$this->authorizer = new FailingDonationAuthorizer();
		$mailer->expects( $this->never() )->method( $this->anything() );
		$this->newDonationApprovedEventHandler( $mailer )->onDonationApproved( self::KNOWN_ID );
	}

}
