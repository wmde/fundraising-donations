<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\ModerateDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
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

	public function testGivenNonExistingDonation_approvalFails(): void {
		$fakeDonationRepository = new FakeDonationRepository();
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsNotMarkedForModeration_approvalFails(): void {
		$fakeDonationRepository = new FakeDonationRepository( ValidDonation::newBankTransferDonation() );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatWasMarkedForModeration_approvalSucceeds(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertFalse( $donation->isMarkedForModeration() );
	}

	public function testApprovalOfDonationIsPersisted(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$persistedDonation = $fakeDonationRepository->getDonationById( $donation->getId() );
		$this->assertFalse( $persistedDonation->isMarkedForModeration() );
	}

	public function testWhenModeratedDonationGotApproved_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$useCase->approveDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertSame(
			[ [ $donation->getId(), 'marked as approved by user: coolAdmin' ] ],
			$donationLogger->getLogCalls()
		);
	}

	public function testGivenNonExistingDonation_markingForModerationFails(): void {
		$fakeDonationRepository = new FakeDonationRepository();
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsAlreadyMarkedForModeration_markingForModerationFails(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->moderationChangeSucceeded() );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsNotModerated_markingForModerationSucceeds(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	public function testModerationMarkerOfDonationIsPersisted(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$useCase->markDonationAsModerated( $donation->getId(), self::AUTH_USER_NAME );

		$persistedDonation = $fakeDonationRepository->getDonationById( $donation->getId() );
		$this->assertTrue( $persistedDonation->isMarkedForModeration() );
	}

	public function testWhenDonationGetsMarkedForModeration_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->markDonationAsModerated( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertSame(
			[ [ $response->getDonationId(), 'marked for moderation by user: coolAdmin' ] ],
			$donationLogger->getLogCalls()
		);
	}

	public function testApprovedDonation_canBeMarkedForModerationAgain(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->markForModeration();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->approveDonation( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertFalse( $donation->isMarkedForModeration() );

		$useCase = new ModerateDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->markDonationAsModerated( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->moderationChangeSucceeded() );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}
}
