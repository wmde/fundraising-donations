<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\ModerateDonation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\NotificationLogStub;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\ModerateDonationResponse;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\ModerateDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\NotificationLog;

#[CoversClass( ModerateDonationUseCase::class )]
#[CoversClass( ModerateDonationResponse::class )]
class ModerateDonationUseCaseTest extends TestCase {

	private const AUTH_USER_NAME = "coolAdmin";

	private DonationEventLoggerSpy $donationLogger;

	private Stub&DonationNotifier $notifier;

	private NotificationLog $notificationLog;

	protected function setUp(): void {
		parent::setUp();
		$this->donationLogger = new DonationEventLoggerSpy();
		$this->notifier = $this->createStub( DonationNotifier::class );
		$this->notificationLog = new NotificationLogStub();
	}

	public function testGivenNonExistingDonation_approvalFails(): void {
		$useCase = $this->newModerateDonationUseCase();
		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $this->donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsNotMarkedForModeration_approvalFails(): void {
		$useCase = $this->newModerateDonationUseCase( ValidDonation::newBankTransferDonation() );

		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $this->donationLogger->getLogCalls() );
	}

	private function makeGenericModerationReason(): ModerationReason {
		return new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
	}

	public function testGivenDonationThatWasMarkedForModeration_approvalSucceeds(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$useCase = $this->newModerateDonationUseCase( $donation );

		$response = $useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertFalse( $donation->isMarkedForModeration() );
	}

	public function testApprovalOfDonationIsPersisted(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$donationRepositorySpy = new DonationRepositorySpy( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $donationRepositorySpy, $donationLogger, $this->notifier, $this->notificationLog );
		$response = $useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$storeCalls = $donationRepositorySpy->getStoreDonationCalls();
		$this->assertCount( 1, $storeCalls );
		$this->assertSame( $donation->getId(), $storeCalls[0]->getId() );
	}

	public function testWhenModeratedDonationGotApproved_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$useCase = $this->newModerateDonationUseCase( $donation );

		$useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertSame(
			[ [ $donation->getId(), 'marked as approved by user: coolAdmin' ] ],
			$this->donationLogger->getLogCalls()
		);
	}

	public function testWhenModeratedDonationGotApproved_donorIsNotified(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );

		$this->notifier = $this->createMock( DonationNotifier::class );
		$this->notifier->expects( $this->once() )->method( 'sendConfirmationFor' )->with( $donation );

		$useCase = $this->newModerateDonationUseCase( $donation );
		$useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );
	}

	public function testWhenModeratedDonationGotApproved_notificationIsLogged(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$this->notificationLog = $this->createMock( NotificationLog::class );
		$useCase = $this->newModerateDonationUseCase( $donation );

		$this->notificationLog->expects( $this->once() )->method( 'logConfirmationSent' )->with( $donation->getId() );

		$useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );
	}

	public function testWhenModeratedDonationGotApprovedWithNotificationAlreadySent_donorIsNotNotified(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$this->notificationLog = $this->createMock( NotificationLog::class );
		$this->notificationLog->method( 'hasSentConfirmationFor' )->willReturn( true );
		$this->notificationLog->expects( $this->never() )->method( 'logConfirmationSent' );
		$this->notifier = $this->createMock( DonationNotifier::class );
		$this->notifier->expects( $this->never() )->method( 'sendConfirmationFor' );

		$useCase = $this->newModerateDonationUseCase( $donation );
		$useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );
	}

	public function testGivenNonExistingDonation_markingForModerationFails(): void {
		$useCase = $this->newModerateDonationUseCase();

		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $this->donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsAlreadyMarkedForModeration_markingForModerationFails(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$useCase = $this->newModerateDonationUseCase( $donation );

		$response = $useCase->markDonationAsModerated( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $this->donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsNotModerated_markingForModerationSucceeds(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$useCase = $this->newModerateDonationUseCase( $donation );
		$response = $useCase->markDonationAsModerated( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	public function testModerationMarkerOfDonationIsPersisted(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donationRepositorySpy = new DonationRepositorySpy( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $donationRepositorySpy, $donationLogger, $this->notifier, $this->notificationLog );
		$response = $useCase->markDonationAsModerated( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$storeCalls = $donationRepositorySpy->getStoreDonationCalls();
		$this->assertCount( 1, $storeCalls );
		$this->assertSame( $donation->getId(), $storeCalls[0]->getId() );
	}

	public function testWhenDonationGetsMarkedForModeration_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$useCase = $this->newModerateDonationUseCase( $donation );

		$response = $useCase->markDonationAsModerated( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertSame(
			[ [ $response->getDonationId(), 'marked for moderation by user: coolAdmin' ] ],
			$this->donationLogger->getLogCalls()
		);
	}

	public function testApprovedDonation_canBeMarkedForModerationAgain(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		$useCase = $this->newModerateDonationUseCase( $donation );
		$response = $useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertFalse( $donation->isMarkedForModeration() );

		$response = $useCase->markDonationAsModerated( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	private function newModerateDonationUseCase( Donation ...$donations ): ModerateDonationUseCase {
		return new ModerateDonationUseCase( new FakeDonationRepository( ...$donations ), $this->donationLogger, $this->notifier, $this->notificationLog );
	}
}
