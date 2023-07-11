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
 */
class DoctrineCommentFinderTest extends TestCase {

	private const COMMENT_NAME = 'Donor name';
	private const COMMENT = 'Comment';
	private const DONATION_AMOUNT = '100';
	private const DONATION_AMOUNT_FLOAT = 100;
	private EntityManager $entityManager;

	public function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
		parent::setUp();
	}

	private function newDbalCommentRepository(): DoctrineCommentFinder {
		return new DoctrineCommentFinder( $this->entityManager );
	}

	public function testWhenThereAreNoComments_anEmptyListIsReturned(): void {
		$repository = $this->newDbalCommentRepository();

		$this->assertCount( 0, $repository->getPublicComments( 10 ) );
	}

	public function testWhenThereAreLessCommentsThanTheLimit_theyAreAllReturned(): void {
		$this->givenStoredDonationWithComment( donationId: 1, date: '1984-01-01' );
		$this->givenStoredDonationWithComment( donationId: 2, date: '1984-01-02' );
		$this->givenStoredDonationWithComment( donationId: 3, date: '1984-01-03' );
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getComment( donationId: 3, date: '1984-01-03' ),
				$this->getComment( donationId: 2, date: '1984-01-02' ),
				$this->getComment( donationId: 1, date: '1984-01-01' ),
			],
			$repository->getPublicComments( 10 )
		);
	}

	public function testWhenThereAreMoreCommentsThanTheLimit_aLimitedNumberAreReturned(): void {
		$this->givenStoredDonationWithComment( donationId: 1, date: '1984-01-01' );
		$this->givenStoredDonationWithComment( donationId: 2, date: '1984-01-02' );
		$this->givenStoredDonationWithComment( donationId: 3, date: '1984-01-03' );
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getComment( donationId: 3, date: '1984-01-03' ),
				$this->getComment( donationId: 2, date: '1984-01-02' ),
			],
			$repository->getPublicComments( 2 )
		);
	}

	public function testOnlyPublicCommentsGetReturned(): void {
		$this->givenStoredDonationWithComment( donationId: 1, date: '1984-01-01' );
		$this->givenStoredDonationWithComment( donationId: 2, date: '1984-01-02' );
		$this->givenStoredDonationWithPrivateComment( donationId: 3, date: '1984-01-03' );
		$this->givenStoredDonationWithComment( donationId: 4, date: '1984-01-04' );
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getComment( donationId: 4, date: '1984-01-04' ),
				$this->getComment( donationId: 2, date: '1984-01-02' ),
				$this->getComment( donationId: 1, date: '1984-01-01' ),
			],
			$repository->getPublicComments( 10 )
		);
	}

	public function testOnlyNonDeletedCommentsGetReturned(): void {
		$this->givenStoredDonationWithComment( donationId: 1, date: '1984-01-01' );
		$this->givenStoredDonationWithComment( donationId: 2, date: '1984-01-02' );
		$this->givenDeletedTimeStoredDonationWithComment( donationId: 3, createdDate: '1984-01-03', deletedDate: '2000-01-01' );
		$this->givenStoredDonationWithComment( donationId: 4, date: '1984-01-04' );
		$this->givenDeletedStatusStoredDonationWithComment( donationId: 5, createdDate: '1984-01-05' );
		$this->entityManager->flush();

		$repository = $this->newDbalCommentRepository();

		$this->assertEquals(
			[
				$this->getComment( donationId: 4, date: '1984-01-04' ),
				$this->getComment( donationId: 2, date: '1984-01-02' ),
				$this->getComment( donationId: 1, date: '1984-01-01' ),
			],
			$repository->getPublicComments( 10 )
		);
	}

	public function testDoctrineThrowsException_getPublicCommentsRethrowsAsDomainException(): void {
		$repository = new DoctrineCommentFinder( $this->newThrowingEntityManager() );

		$this->expectException( CommentListingException::class );
		$repository->getPublicComments( 10 );
	}

	public function testGivenOffsetOfOneCausesOneCommentToBeSkipped(): void {
		$this->givenStoredDonationWithComment( donationId: 1, date: '1984-01-01' );
		$this->givenStoredDonationWithComment( donationId: 2, date: '1984-01-02' );
		$this->givenStoredDonationWithComment( donationId: 3, date: '1984-01-03' );
		$this->entityManager->flush();

		$this->assertEquals(
			[
				$this->getComment( donationId: 2, date: '1984-01-02' ),
				$this->getComment( donationId: 1, date: '1984-01-01' ),
			],
			$this->newDbalCommentRepository()->getPublicComments( 10, 1 )
		);
	}

	public function testGivenOffsetBeyondResultSetCausesEmptyResult(): void {
		$this->givenStoredDonationWithComment( donationId: 1, date: '1984-01-01' );
		$this->givenStoredDonationWithComment( donationId: 2, date: '1984-01-02' );
		$this->givenStoredDonationWithComment( donationId: 3, date: '1984-01-03' );
		$this->entityManager->flush();

		$this->assertEquals(
			[],
			$this->newDbalCommentRepository()->getPublicComments( 10, 10 )
		);
	}

	private function givenDonation( int $donationId, string $date ): Donation {
		$donation = new Donation();
		$donation->setId( $donationId );
		$donation->setPaymentId( $donationId );
		$donation->setPublicRecord( self::COMMENT_NAME );
		$donation->setComment( self::COMMENT );
		$donation->setAmount( self::DONATION_AMOUNT );
		$donation->setCreationTime( new DateTime( $date ) );
		$donation->setIsPublic( true );
		return $donation;
	}

	private function givenStoredDonationWithComment( int $donationId, string $date ): void {
		$this->entityManager->persist( $this->givenDonation( $donationId, $date ) );
	}

	private function givenStoredDonationWithPrivateComment( int $donationId, string $date ): void {
		$donation = $this->givenDonation( $donationId, $date );
		$donation->setIsPublic( false );
		$this->entityManager->persist( $donation );
	}

	private function givenDeletedTimeStoredDonationWithComment( int $donationId, string $createdDate, string $deletedDate ): void {
		$donation = $this->givenDonation( $donationId, $createdDate );
		$donation->setDeletionTime( new DateTime( $deletedDate ) );
		$this->entityManager->persist( $donation );
	}

	private function givenDeletedStatusStoredDonationWithComment( int $donationId, string $createdDate ): void {
		$donation = $this->givenDonation( $donationId, $createdDate );
		$donation->setStatus( Donation::STATUS_CANCELLED );
		$this->entityManager->persist( $donation );
	}

	private function getComment( int $donationId, string $date ): CommentWithAmount {
		return CommentWithAmount::newInstance()
			->setAuthorName( self::COMMENT_NAME )
			->setCommentText( self::COMMENT )
			->setDonationAmount( self::DONATION_AMOUNT_FLOAT )
			->setDonationTime( new \DateTime( $date ) )
			->setDonationId( $donationId )
			->freeze()->assertNoNullFields();
	}

	private function newThrowingEntityManager(): EntityManager {
		$entityManager = $this->createMock( EntityManager::class );

		$entityManager->expects( $this->any() )
			->method( $this->anything() )
			->willThrowException( new ORMException( 'Such error!' ) );

		return $entityManager;
	}
}
