<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\ModerateDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\ModerateDonationUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\ModerateDonationUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\ModerateDonationResponse
 *
 * @license GPL-2.0-or-later
 */
class ModerateDonationUseCaseTest extends TestCase {

	private const AUTH_USER_NAME = "coolAdmin";

	private DonationEventLogger $donationLogger;

	protected function setUp(): void {
		parent::setUp();
		$this->donationLogger = new DonationEventLoggerSpy();
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

	public function testGivenDonationThatWasMarkedForModeration_approvalSucceeds(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$useCase = $this->newModerateDonationUseCase( $donation );

		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertFalse( $donation->isMarkedForModeration() );
	}

	public function testApprovalOfDonationIsPersisted(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$donationRepositorySpy = new DonationRepositorySpy( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $donationRepositorySpy, $donationLogger );
		$response = $useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$storeCalls = $donationRepositorySpy->getStoreDonationCalls();
		$this->assertCount( 1, $storeCalls );
		$this->assertSame( $donation->getId(), $storeCalls[0]->getId() );
	}

	public function testWhenModeratedDonationGotApproved_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$useCase = $this->newModerateDonationUseCase( $donation );

		$useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertSame(
			[ [ $donation->getId(), 'marked as approved by user: coolAdmin' ] ],
			$this->donationLogger->getLogCalls()
		);
	}

	public function testGivenNonExistingDonation_markingForModerationFails(): void {
		$useCase = $this->newModerateDonationUseCase();

		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $this->donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsAlreadyMarkedForModeration_markingForModerationFails(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$useCase = $this->newModerateDonationUseCase( $donation );

		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $this->donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsNotModerated_markingForModerationSucceeds(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$useCase = $this->newModerateDonationUseCase( $donation );
		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	public function testModerationMarkerOfDonationIsPersisted(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donationRepositorySpy = new DonationRepositorySpy( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $donationRepositorySpy, $donationLogger );
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
		$donation->markForModeration();
		$useCase = $this->newModerateDonationUseCase( $donation );
		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertFalse( $donation->isMarkedForModeration() );

		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	private function newModerateDonationUseCase( Donation ...$donations ): ModerateDonationUseCase {
		return new ModerateDonationUseCase( new FakeDonationRepository( ...$donations ), $this->donationLogger );
	}
}
