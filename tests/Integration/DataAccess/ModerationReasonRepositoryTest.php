<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\ModerationReasonRepository;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\ModerationReasonRepository
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineTypes\ModerationIdentifier
 */
class ModerationReasonRepositoryTest extends TestCase {
	private EntityManager $entityManager;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->entityManager = $factory->getEntityManager();
		parent::setUp();
	}

	public function testGivenStoredModerationReasons_itReturnsOnlyRequestedModerationReasons(): void {
		$repository = new ModerationReasonRepository( $this->entityManager );
		$moderationReason1 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
		$moderationReason2 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN, 'firstname' );
		$moderationReason3 = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'lastname' );
		$this->entityManager->persist( $moderationReason1 );
		$this->entityManager->persist( $moderationReason2 );
		$this->entityManager->persist( $moderationReason3 );
		$this->entityManager->flush();

		$requestedModerationReason1 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
		$requestedModerationReason2 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN, 'firstname' );
		$result = $repository->getModerationReasonsThatAreAlreadyPersisted( $requestedModerationReason1, $requestedModerationReason2 );

		$this->assertCount( 2, $result );
		$this->assertContains( $moderationReason1, $result );
		$this->assertContains( $moderationReason2, $result );
	}

	public function testGivenRequestingMoreReasonsThanPersisted_itReturnsOnlyAlreadyExistingModerationReasons(): void {
		$repository = new ModerationReasonRepository( $this->entityManager );
		$moderationReason1 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
		$this->entityManager->persist( $moderationReason1 );
		$this->entityManager->flush();

		$requestedModerationReason1 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
		$requestedModerationReason2 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN, 'firstname' );
		$result = $repository->getModerationReasonsThatAreAlreadyPersisted( $requestedModerationReason1, $requestedModerationReason2 );

		$this->assertCount( 1, $result );
		$this->assertContains( $moderationReason1, $result );
	}

	public function testGivenRequestingNoReasons_itReturnsNoReasons(): void {
		$repository = new ModerationReasonRepository( $this->entityManager );
		$moderationReason1 = new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
		$this->entityManager->persist( $moderationReason1 );
		$this->entityManager->flush();

		$result = $repository->getModerationReasonsThatAreAlreadyPersisted();

		$this->assertCount( 0, $result );
	}
}
