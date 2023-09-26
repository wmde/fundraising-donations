<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\DonationAcceptedEventHandler;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;

/**
 * @covers \WMDE\Fundraising\DonationContext\DonationAcceptedEventHandler
 */
class DonationAcceptedEventHandlerTest extends TestCase {

	private const UNKNOWN_ID = 32202;
	private const KNOWN_ID = 31337;

	private DonationAuthorizer $authorizer;
	private DonationRepository $repository;
	private MockObject&DonationNotifier $mailer;

	public function setUp(): void {
		$this->authorizer = new SucceedingDonationAuthorizer();
		$this->repository = new FakeDonationRepository( $this->newDonation() );
		$this->mailer = $this->createMock( DonationNotifier::class );
	}

	private function newDonation(): Donation {
		return ValidDonation::newBankTransferDonation( self::KNOWN_ID );
	}

	public function testWhenAuthorizationFails_errorIsReturned(): void {
		$this->authorizer = new FailingDonationAuthorizer();
		$eventHandler = $this->newDonationAcceptedEventHandler();

		$result = $eventHandler->onDonationAccepted( self::UNKNOWN_ID );

		$this->assertSame( DonationAcceptedEventHandler::AUTHORIZATION_FAILED, $result );
	}

	private function newDonationAcceptedEventHandler(): DonationAcceptedEventHandler {
		return new DonationAcceptedEventHandler(
			$this->authorizer,
			$this->repository,
			$this->mailer
		);
	}

	public function testGivenIdOfUnknownDonation_errorIsReturned(): void {
		$eventHandler = $this->newDonationAcceptedEventHandler();

		$result = $eventHandler->onDonationAccepted( self::UNKNOWN_ID );

		$this->assertSame( DonationAcceptedEventHandler::UNKNOWN_ID_PROVIDED, $result );
	}

	public function testGivenKnownIdAndValidAuth_successIsReturned(): void {
		$eventHandler = $this->newDonationAcceptedEventHandler();

		$result = $eventHandler->onDonationAccepted( self::KNOWN_ID );

		$this->assertSame( DonationAcceptedEventHandler::SUCCESS, $result );
	}

	public function testGivenKnownIdAndValidAuth_mailerIsInvoked(): void {
		$this->mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $this->newDonation() );

		$this->newDonationAcceptedEventHandler()->onDonationAccepted( self::KNOWN_ID );
	}

	public function testGivenIdOfUnknownDonation_mailerIsNotInvoked(): void {
		$this->mailer->expects( $this->never() )->method( $this->anything() );
		$this->newDonationAcceptedEventHandler()->onDonationAccepted( self::UNKNOWN_ID );
	}

	public function testWhenAuthorizationFails_mailerIsNotInvoked(): void {
		$this->authorizer = new FailingDonationAuthorizer();
		$this->mailer->expects( $this->never() )->method( $this->anything() );
		$this->newDonationAcceptedEventHandler()->onDonationAccepted( self::KNOWN_ID );
	}

}
