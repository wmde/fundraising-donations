<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\UpdateDonor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\NotificationLogStub;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\ModerateDonationUseCase;

#[CoversClass( ModerateDonationUseCase::class )]
class ApproveAsAnonymousUseCaseTest extends TestCase {

	public function testApproveAsAnonymous_persistsChangesInRepository(): void {
		$repository = $this->newRepository();

		$donation = ValidDonation::newBankTransferDonation();

		$donation->markForModeration(
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION )
		);

		$repository->storeDonation( $donation );

		$useCase = $this->newApproveAsAnonymousUseCase( $repository );
		$useCase->approveAsAnonymous( $donation->getId(), 'adminUser' );

		$updatedAnonymisedDonation = $repository->getDonationById( $donation->getId() );

		$this->assertNotNull( $updatedAnonymisedDonation );

		$this->assertSame( $donation->getId(), $updatedAnonymisedDonation->getId() );

		$this->assertInstanceOf( AnonymousDonor::class, $updatedAnonymisedDonation->getDonor() );

		$this->assertFalse( $updatedAnonymisedDonation->isMarkedForModeration() );
	}

	private function newRepository(): DonationRepository {
		return new FakeDonationRepository();
	}

	private function newApproveAsAnonymousUseCase( DonationRepository $repository ): ModerateDonationUseCase {
		return new ModerateDonationUseCase(
			$repository,
			new DonationEventLoggerSpy(),
			$this->createStub( DonationNotifier::class ),
			new NotificationLogStub()
		);
	}
}
