<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\ModerationReasonRepository;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedTokenGenerator;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DonorFieldMapper
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DonorFactory
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\DomainToLegacyConverter
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter
 *
 * @license GPL-2.0-or-later
 */
class DoctrineDonationRepositoryTest extends TestCase {

	private const ID_OF_DONATION_NOT_IN_DB = 35505;

	private EntityManager $entityManager;
	private ModerationReasonRepository $moderationRepository;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->entityManager = $factory->getEntityManager();
		$this->moderationRepository = new ModerationReasonRepository( $this->entityManager );
		parent::setUp();
	}

	public function testValidDonationGetPersisted(): void {
		$this->markTestIncomplete( 'This should work again when legacy converters have been fixed' );
		$donation = ValidDonation::newDirectDebitDonation();

		$this->newRepository()->storeDonation( $donation );

		$expectedDoctrineEntity = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$expectedDoctrineEntity->setId( $donation->getId() );

		$this->assertDoctrineEntityIsInDatabase( $expectedDoctrineEntity );
	}

	private function newRepository(): DoctrineDonationRepository {
		return new DoctrineDonationRepository(
			$this->entityManager,
			$this->makeGetPaymentUseCaseStub(),
			$this->moderationRepository
		);
	}

	private function assertDoctrineEntityIsInDatabase( DoctrineDonation $expected ): void {
		$actual = $this->getDoctrineDonationById( $expected->getId() );

		// creationTime is autogenerated when saving, so we check if it was autogenerated
		// and then modify our expected value (where it's null) to match the date, so the comparison succeeds
		$this->assertNotNull( $actual->getCreationTime() );
		$expected->setCreationTime( $actual->getCreationTime() );

		// pre-persist subscriber automatically access and update tokens. We're using fixed values in the test
		$expected->encodeAndSetData( array_merge( $expected->getDecodedData(), [
			'token' => FixedTokenGenerator::TOKEN,
			'utoken' => FixedTokenGenerator::TOKEN,
			'uexpiry' => FixedTokenGenerator::EXPIRY_DATE
		] ) );

		$this->assertEquals( $expected->getModerationReasons()->toArray(), $actual->getModerationReasons()->toArray() );
		$this->assertEquals( $expected->getDecodedData(), $actual->getDecodedData() );

		// reset the moderation reasons because doctrine sets the moderation reasons to a PersistedCollection instead of ArrayCollection
		// this way we can compare the objects
		$actual->setModerationReasons( ...$expected->getModerationReasons()->toArray() );
		$this->assertEquals( $expected, $actual );
	}

	private function getDoctrineDonationById( int $id ): DoctrineDonation {
		$donationRepo = $this->entityManager->getRepository( DoctrineDonation::class );
		$donation = $donationRepo->find( $id );
		$this->assertInstanceOf( DoctrineDonation::class, $donation );
		return $donation;
	}

	public function testWhenInsertFails_domainExceptionIsThrown(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$repository = new DoctrineDonationRepository(
			ThrowingEntityManager::newInstance( $this ),
			$this->makeGetPaymentUseCaseStub(),
			$this->moderationRepository
		);

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testNewDonationPersistenceRoundTrip(): void {
		$this->markTestIncomplete( 'This should work again when legacy converters have been fixed' );
		$donation = ValidDonation::newDirectDebitDonation();

		$repository = $this->newRepository();

		$repository->storeDonation( $donation );
		// find() will retrieve a cached value, so we should clear the entity cache here
		$this->entityManager->clear();

		$this->assertEquals(
			$donation,
			$repository->getDonationById( $donation->getId() )
		);
	}

	public function testNewModeratedDonationPersistenceRoundTrip(): void {
		$this->markTestIncomplete( 'This should work again when legacy converters have been fixed.' );
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->markForModeration( new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ) );

		$repository = $this->newRepository();

		$repository->storeDonation( $donation );
		// find() will retrieve a cached value, so we should clear the entity cache here
		$this->entityManager->clear();

		$this->assertEquals(
			$donation->getModerationReasons(),
			$repository->getDonationById( $donation->getId() )->getModerationReasons()
		);
	}

	public function testWhenDonationAlreadyExists_persistingCausesUpdate(): void {
		// TODO for Refactoring: Pay close attention to the payment ID - the entity factory will create a new payment for the detached
		//  entity, which should not cause any trouble, because the repository should not try to store payment data when the donation changes.
		$this->markTestIncomplete( 'This should work again when legacy converters have been fixed.' );
		$repository = $this->newRepository();

		$donation = ValidDonation::newDirectDebitDonation();
		$repository->storeDonation( $donation );

		// It is important a new instance is created here to test "detached entity" handling
		$newDonation = ValidDonation::newDirectDebitDonation();
		$newDonation->assignId( $donation->getId() );
		$newDonation->cancel();
		$repository->storeDonation( $newDonation );

		$this->assertEquals( $newDonation, $repository->getDonationById( $newDonation->getId() ) );
	}

	public function testWhenDonationDoesNotExist_getDonationReturnsNull(): void {
		$repository = $this->newRepository();

		$this->assertNull( $repository->getDonationById( self::ID_OF_DONATION_NOT_IN_DB ) );
	}

	public function testWhenDoctrineThrowsException_domainExceptionIsThrown(): void {
		$repository = new DoctrineDonationRepository(
			ThrowingEntityManager::newInstance( $this ),
			$this->makeGetPaymentUseCaseStub(),
			$this->moderationRepository
		);

		$this->expectException( GetDonationException::class );
		$repository->getDonationById( self::ID_OF_DONATION_NOT_IN_DB );
	}

	public function testWhenDonationDoesNotExist_persistingCausesException(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( self::ID_OF_DONATION_NOT_IN_DB );

		$repository = $this->newRepository();

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testGivenDonationUpdateWithoutDonorInformation_DonorNameStaysTheSame(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$this->newRepository()->storeDonation( $donation );

		$anonymousDonation = ValidDonation::newBookedAnonymousPayPalDonationUpdate( $donation->getId() );
		$this->newRepository()->storeDonation( $anonymousDonation );

		$doctrineDonation = $this->getDoctrineDonationById( $donation->getId() );

		$this->assertSame( $donation->getDonor()->getName()->getFullName(), $doctrineDonation->getDonorFullName() );
	}

	public function testCommentGetPersistedAndRetrieved(): void {
		$this->markTestIncomplete( 'This should work again when legacy converters have been fixed.' );
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$repository = $this->newRepository();
		$repository->storeDonation( $donation );

		$retrievedDonation = $repository->getDonationById( $donation->getId() );

		$this->assertEquals( $donation, $retrievedDonation );
	}

	public function testPersistingDonationWithoutCommentCausesCommentToBeCleared(): void {
		$this->markTestIncomplete( 'This should work again when legacy converters have been fixed.' );
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$repository = $this->newRepository();
		$repository->storeDonation( $donation );

		$newDonation = ValidDonation::newDirectDebitDonation();
		$newDonation->assignId( $donation->getId() );

		$repository->storeDonation( $newDonation );

		$expectedDoctrineEntity = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$expectedDoctrineEntity->setId( $donation->getId() );
		$expectedDoctrineEntity->setComment( '' );
		$expectedDoctrineEntity->setIsPublic( false );
		$expectedDoctrineEntity->setPublicRecord( '' );

		$this->assertDoctrineEntityIsInDatabase( $expectedDoctrineEntity );
	}

	public function testWhenUpdateFails_domainExceptionIsThrown(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( 42 );

		$repository = new DoctrineDonationRepository(
			ThrowingEntityManager::newInstance( $this ),
			$this->makeGetPaymentUseCaseStub(),
			$this->moderationRepository
		);

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function makeGetPaymentUseCaseStub(): GetPaymentUseCase {
		$stub = $this->createStub( GetPaymentUseCase::class );
		$stub->method( 'getLegacyPaymentDataObject' )->willReturn(
			new LegacyPaymentData(
				999999,
				999,
				'BLA',
				[],
				'*'
			)
		);
		return $stub;
	}

	public function testGivenTwoDonationsWithTheSameModerationReason_ReasonIsNotCreatedMultipleTimesButReused(): void {
		$donation1 = ValidDonation::newDirectDebitDonation();
		$donation1->markForModeration( new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ) );
		$donation2 = ValidDonation::newDirectDebitDonation();
		$donation2->markForModeration( new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ) );

		$repository = $this->newRepository();
		$repository->storeDonation( $donation1 );
		$repository->storeDonation( $donation2 );

		$connection = $this->entityManager->getConnection();
		$result = $connection->executeQuery( "SELECT COUNT(*) FROM donation_moderation_reason " );
		$this->assertSame( 1, $result->fetchOne() );
	}
}
