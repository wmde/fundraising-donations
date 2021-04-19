<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\RestoreDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\UseCases\RestoreDonation\RestoreDonationUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\RestoreDonation\RestoreDonationUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\RestoreDonation\RestoreDonationResponse
 *
 * @license GPL-2.0-or-later
 */
class RestoreDonationUseCaseTest extends TestCase {

	private const AUTH_USER_NAME = "coolAdmin";

	public function testGivenNonExistingDonation_restoreFails(): void {
		$fakeDonationRepository = new FakeDonationRepository();
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new RestoreDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->restoreCancelledDonation( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->restoreSucceeded() );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenDonationThatIsNotCancelled_restoreFails(): void {
		$fakeDonationRepository = new FakeDonationRepository( ValidDonation::newBankTransferDonation() );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new RestoreDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->restoreCancelledDonation( 1, self::AUTH_USER_NAME );

		$this->assertFalse( $response->restoreSucceeded() );
		$this->assertCount( 0, $donationLogger->getLogCalls() );
	}

	public function testGivenCancelledDonation_restoreSucceeds(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new RestoreDonationUseCase( $fakeDonationRepository, $donationLogger );
		$response = $useCase->restoreCancelledDonation( 1, self::AUTH_USER_NAME );

		$this->assertTrue( $response->restoreSucceeded() );
		$this->assertFalse( $donation->isCancelled() );
	}

	public function testRestoredDonationIsPersisted(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new RestoreDonationUseCase( $fakeDonationRepository, $donationLogger );
		$useCase->restoreCancelledDonation( $donation->getId(), self::AUTH_USER_NAME );

		$persistedDonation = $fakeDonationRepository->getDonationById( $donation->getId() );
		$this->assertFalse( $persistedDonation->isCancelled() );
	}

	public function testWhenCancelledDonationGetsRestored_adminUserNameIsWrittenAsLogEntry(): void {
		$donation = ValidDonation::newCancelledBankTransferDonation();
		$fakeDonationRepository = new FakeDonationRepository( $donation );
		$donationLogger = new DonationEventLoggerSpy();

		$useCase = new RestoreDonationUseCase( $fakeDonationRepository, $donationLogger );
		$useCase->restoreCancelledDonation( $donation->getId(), self::AUTH_USER_NAME );

		$this->assertSame(
			[ [ $donation->getId(), 'restored by user: coolAdmin' ] ],
			$donationLogger->getLogCalls()
		);
	}

}
