<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineCommentFinder;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentListingException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentWithAmount;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

/**
 * @covers WMDE\Fundraising\DonationContext\DataAccess\DoctrineCommentFinder
 *
 * TODO: Refactor this test, it is confusing as it relies on magic database ID incrementation
 */
class DoctrineCommentFinderTest extends TestCase {

	private const DUMMY_PAYMENT_ID = 42;
	private EntityManager $entityManager;
	private int $currentId = 0;

	public function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
		parent::setUp();
		$this->currentId = 0;
	}

	private function getIncrementedCurrentId(): int {
		$this->currentId++;
		return $this->currentId;
	}

	private function newDbalCommentRepository(): DoctrineCommentFinder {
		return new DoctrineCommentFinder( $this->entityManager );
	}

	public function testWhenThereAreNoComments_anEmptyListIsReturned(): void {
		$repository = $this->newDbalCommentRepository();

		$this->assertCount( 0, $repository->getPublicComments( 10 ) );
	}

	public function testWhenThereAreLessCommentsThanTheLimit_theyAreAllReturned(): void {
		$this->persistFirstDonationWithComment();
		$this->persistSecondDonationWithComment();
		$this->persistThirdDonationWithComment();
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getThirdComment( 3 ),
				$this->getSecondComment(),
				$this->getFirstComment(),
			],
			$repository->getPublicComments( 10 )
		);
	}

	public function testWhenThereAreMoreCommentsThanTheLimit_aLimitedNumberAreReturned(): void {
		$this->persistFirstDonationWithComment();
		$this->persistSecondDonationWithComment();
		$this->persistThirdDonationWithComment();
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getThirdComment( 3 ),
				$this->getSecondComment(),
			],
			$repository->getPublicComments( 2 )
		);
	}

	public function testOnlyPublicCommentsGetReturned(): void {
		$this->persistFirstDonationWithComment();
		$this->persistSecondDonationWithComment();
		$this->persistDonationWithPrivateComment();
		$this->persistThirdDonationWithComment();
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getThirdComment( 4 ),
				$this->getSecondComment(),
				$this->getFirstComment(),
			],
			$repository->getPublicComments( 10 )
		);
	}

	public function testOnlyNonDeletedCommentsGetReturned(): void {
		$this->persistFirstDonationWithComment();
		$this->persistSecondDonationWithComment();
		$this->persistDeletedDonationWithComment();
		$this->persistThirdDonationWithComment();
		$this->persistDeletedDonationWithoutDeletedTimestamp();
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getThirdComment( 4 ),
				$this->getSecondComment(),
				$this->getFirstComment(),
			],
			$repository->getPublicComments( 10 )
		);
	}

	private function persistFirstDonationWithComment(): void {
		$donation = new Donation();
		$donation->setId( $this->getIncrementedCurrentId() );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$donation->setPublicRecord( 'First name' );
		$donation->setComment( 'First comment' );
		$donation->setAmount( '100' );
		$donation->setCreationTime( new DateTime( '1984-01-01' ) );
		$donation->setIsPublic( true );
		$this->entityManager->persist( $donation );
	}

	private function persistSecondDonationWithComment(): void {
		$donation = new Donation();
		$donation->setId( $this->getIncrementedCurrentId() );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID + 1 );
		$donation->setPublicRecord( 'Second name' );
		$donation->setComment( 'Second comment' );
		$donation->setAmount( '200' );
		$donation->setCreationTime( new DateTime( '1984-02-02' ) );
		$donation->setIsPublic( true );
		$this->entityManager->persist( $donation );
	}

	private function persistThirdDonationWithComment(): void {
		$donation = new Donation();
		$donation->setId( $this->getIncrementedCurrentId() );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID + 2 );
		$donation->setPublicRecord( 'Third name' );
		$donation->setComment( 'Third comment' );
		$donation->setAmount( '300' );
		$donation->setCreationTime( new DateTime( '1984-03-03' ) );
		$donation->setIsPublic( true );
		$this->entityManager->persist( $donation );
	}

	private function persistDonationWithPrivateComment(): void {
		$donation = new Donation();
		$donation->setId( $this->getIncrementedCurrentId() );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$donation->setPublicRecord( 'Private name' );
		$donation->setComment( 'Private comment' );
		$donation->setAmount( '1337' );
		$donation->setCreationTime( new DateTime( '1984-12-12' ) );
		$donation->setIsPublic( false );
		$this->entityManager->persist( $donation );
	}

	private function persistDeletedDonationWithComment(): void {
		$donation = new Donation();
		$donation->setId( $this->getIncrementedCurrentId() );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$donation->setPublicRecord( 'Deleted name' );
		$donation->setComment( 'Deleted comment' );
		$donation->setAmount( '31337' );
		$donation->setCreationTime( new DateTime( '1984-11-11' ) );
		$donation->setIsPublic( true );
		$donation->setDeletionTime( new DateTime( '2000-01-01' ) );
		$this->entityManager->persist( $donation );
	}

	private function persistDeletedDonationWithoutDeletedTimestamp(): void {
		$donation = new Donation();
		$donation->setId( $this->getIncrementedCurrentId() );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$donation->setPublicRecord( 'Deleted name' );
		$donation->setComment( 'Deleted comment' );
		$donation->setAmount( '31337' );
		$donation->setCreationTime( new DateTime( '1984-11-11' ) );
		$donation->setIsPublic( true );
		$donation->setStatus( Donation::STATUS_CANCELLED );
		$this->entityManager->persist( $donation );
	}

	private function getFirstComment(): CommentWithAmount {
		return CommentWithAmount::newInstance()
			->setAuthorName( 'First name' )
			->setCommentText( 'First comment' )
			->setDonationAmount( 100 )
			->setDonationTime( new \DateTime( '1984-01-01' ) )
			->setDonationId( 1 )
			->freeze()->assertNoNullFields();
	}

	private function getSecondComment(): CommentWithAmount {
		return CommentWithAmount::newInstance()
			->setAuthorName( 'Second name' )
			->setCommentText( 'Second comment' )
			->setDonationAmount( 200 )
			->setDonationTime( new \DateTime( '1984-02-02' ) )
			->setDonationId( 2 )
			->freeze()->assertNoNullFields();
	}

	private function getThirdComment( int $donationId ): CommentWithAmount {
		return CommentWithAmount::newInstance()
			->setAuthorName( 'Third name' )
			->setCommentText( 'Third comment' )
			->setDonationAmount( 300 )
			->setDonationTime( new \DateTime( '1984-03-03' ) )
			->setDonationId( $donationId )
			->freeze()->assertNoNullFields();
	}

	public function testDoctrineThrowsException_getPublicCommentsRethrowsAsDomainException(): void {
		$repository = new DoctrineCommentFinder( $this->newThrowingEntityManager() );

		$this->expectException( CommentListingException::class );
		$repository->getPublicComments( 10 );
	}

	private function newThrowingEntityManager(): EntityManager {
		$entityManager = $this->createMock( EntityManager::class );

		$entityManager->expects( $this->any() )
			->method( $this->anything() )
			->willThrowException( new ORMException( 'Such error!' ) );

		return $entityManager;
	}

	public function testGivenOffsetOfOneCausesOneCommentToBeSkipped(): void {
		$this->persistFirstDonationWithComment();
		$this->persistSecondDonationWithComment();
		$this->persistThirdDonationWithComment();
		$this->entityManager->flush();

		$this->assertEquals(
			[
				$this->getSecondComment(),
				$this->getFirstComment(),
			],
			$this->newDbalCommentRepository()->getPublicComments( 10, 1 )
		);
	}

	public function testGivenOffsetBeyondResultSetCausesEmptyResult(): void {
		$this->persistFirstDonationWithComment();
		$this->persistSecondDonationWithComment();
		$this->persistThirdDonationWithComment();
		$this->entityManager->flush();

		$this->assertEquals(
			[],
			$this->newDbalCommentRepository()->getPublicComments( 10, 10 )
		);
	}

}
