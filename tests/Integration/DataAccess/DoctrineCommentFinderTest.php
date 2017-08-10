<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Integration\DataAccess;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use WMDE\Fundraising\Entities\Donation;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineCommentFinder;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\CommentListingException;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\CommentWithAmount;
use WMDE\Fundraising\Frontend\DonationContext\Tests\TestEnvironment;

/**
 * @covers WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineCommentFinder
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DoctrineCommentFinderTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
		parent::setUp();
	}

	private function newDbalCommentRepository(): DoctrineCommentFinder {
		return new DoctrineCommentFinder( $this->entityManager );
	}

	public function testWhenThereAreNoComments_anEmptyListIsReturned(): void {
		$repository = $this->newDbalCommentRepository();

		$this->assertEmpty( $repository->getPublicComments( 10 ) );
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
		$firstDonation = new Donation();
		$firstDonation->setPublicRecord( 'First name' );
		$firstDonation->setComment( 'First comment' );
		$firstDonation->setAmount( '100' );
		$firstDonation->setCreationTime( new DateTime( '1984-01-01' ) );
		$firstDonation->setIsPublic( true );
		$this->entityManager->persist( $firstDonation );
	}

	private function persistSecondDonationWithComment(): void {
		$secondDonation = new Donation();
		$secondDonation->setPublicRecord( 'Second name' );
		$secondDonation->setComment( 'Second comment' );
		$secondDonation->setAmount( '200' );
		$secondDonation->setCreationTime( new DateTime( '1984-02-02' ) );
		$secondDonation->setIsPublic( true );
		$this->entityManager->persist( $secondDonation );
	}

	private function persistThirdDonationWithComment(): void {
		$thirdDonation = new Donation();
		$thirdDonation->setPublicRecord( 'Third name' );
		$thirdDonation->setComment( 'Third comment' );
		$thirdDonation->setAmount( '300' );
		$thirdDonation->setCreationTime( new DateTime( '1984-03-03' ) );
		$thirdDonation->setIsPublic( true );
		$this->entityManager->persist( $thirdDonation );
	}

	private function persistDonationWithPrivateComment(): void {
		$privateDonation = new Donation();
		$privateDonation->setPublicRecord( 'Private name' );
		$privateDonation->setComment( 'Private comment' );
		$privateDonation->setAmount( '1337' );
		$privateDonation->setCreationTime( new DateTime( '1984-12-12' ) );
		$privateDonation->setIsPublic( false );
		$this->entityManager->persist( $privateDonation );
	}

	private function persistDeletedDonationWithComment(): void {
		$deletedDonation = new Donation();
		$deletedDonation->setPublicRecord( 'Deleted name' );
		$deletedDonation->setComment( 'Deleted comment' );
		$deletedDonation->setAmount( '31337' );
		$deletedDonation->setCreationTime( new DateTime( '1984-11-11' ) );
		$deletedDonation->setIsPublic( true );
		$deletedDonation->setDeletionTime( new DateTime( '2000-01-01' ) );
		$this->entityManager->persist( $deletedDonation );
	}

	private function persistDeletedDonationWithoutDeletedTimestamp(): void {
		$deletedDonation = new Donation();
		$deletedDonation->setPublicRecord( 'Deleted name' );
		$deletedDonation->setComment( 'Deleted comment' );
		$deletedDonation->setAmount( '31337' );
		$deletedDonation->setCreationTime( new DateTime( '1984-11-11' ) );
		$deletedDonation->setIsPublic( true );
		$deletedDonation->setStatus( Donation::STATUS_CANCELLED );
		$this->entityManager->persist( $deletedDonation );
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
