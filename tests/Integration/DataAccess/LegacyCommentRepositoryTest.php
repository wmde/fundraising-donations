<?php

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyCommentRepository;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;

#[CoversClass( LegacyCommentRepository::class )]
class LegacyCommentRepositoryTest extends TestCase {

	private const MISSING_COMMENT_ID = 99;
	private const MISSING_DONATION_ID = 999;

	public function testGetCommentByIdIsNotImplemented(): void {
		$repository = new LegacyCommentRepository( new FakeDonationRepository() );
		$this->expectException( LegacyException::class );

		$repository->getCommentById( self::MISSING_COMMENT_ID );
	}

	public function testGetCommentByDonationIdThrowsWhenDonationDoesNotExist(): void {
		$repository = new LegacyCommentRepository( new FakeDonationRepository() );
		$this->expectException( GetDonationException::class );

		$repository->getCommentByDonationId( self::MISSING_DONATION_ID );
	}

	public function testGivenDonationWithoutComment_getCommentByDonationReturnsNull(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$repository = new LegacyCommentRepository( new FakeDonationRepository( $donation ) );

		$comment = $repository->getCommentByDonationId( 1 );

		$this->assertNull( $comment );
	}

	public function testGivenDonationWihComment_getCommentByDonationReturnsComments(): void {
		$donation = ValidDonation::newBookedCreditCardDonation();
		$donation->addComment( ValidDonation::newPublicComment() );
		$repository = new LegacyCommentRepository( new FakeDonationRepository( $donation ) );

		$comment = $repository->getCommentByDonationId( 1 );

		$this->assertEquals( $comment, ValidDonation::newPublicComment() );
	}

	public function testInsertCommentForDonationFailsWhenDonationDoesNotExist(): void {
		$comment = ValidDonation::newPublicComment();
		$donationRepository = new FakeDonationRepository();
		$repository = new LegacyCommentRepository( $donationRepository );
		$this->expectException( GetDonationException::class );

		$repository->insertCommentForDonation( self::MISSING_DONATION_ID, $comment );
	}

	public function testInsertCommentForDonationAddsCommentToDonationAndStoresIt(): void {
		$donation = ValidDonation::newBookedCreditCardDonation();
		$comment = ValidDonation::newPublicComment();
		$donationRepository = new DonationRepositorySpy( $donation );
		$repository = new LegacyCommentRepository( $donationRepository );

		$repository->insertCommentForDonation( 1, $comment );

		$this->assertEquals( $donation->getComment(), ValidDonation::newPublicComment() );
		$storeDonationCalls = $donationRepository->getGetDonationCalls();
		$this->assertCount( 1, $storeDonationCalls );
		$this->assertSame( 1, $storeDonationCalls[0] );
	}

	public function testInsertCommentForDonationFakesCommentIdByReturningDonationId(): void {
		$donation = ValidDonation::newBookedCreditCardDonation();
		$comment = ValidDonation::newPublicComment();
		$donationRepository = new DonationRepositorySpy( $donation );
		$repository = new LegacyCommentRepository( $donationRepository );

		$fakeCommentId = $repository->insertCommentForDonation( 1, $comment );

		$this->assertSame( 1, $fakeCommentId );
	}

	public function testUpdateCommentFailsIfDonationWasNotLoadedBefore(): void {
		$repository = new LegacyCommentRepository( new FakeDonationRepository() );
		$this->expectException( LegacyException::class );

		$repository->updateComment( ValidDonation::newPublicComment() );
	}

	public function testUpdateCommentStoresDonationIfDonationWasLoadedBefore(): void {
		$donation = ValidDonation::newBookedCreditCardDonation();
		$comment = ValidDonation::newPublicComment();
		$donation->addComment( $comment );
		$donationRepository = new DonationRepositorySpy( $donation );
		$repository = new LegacyCommentRepository( $donationRepository );

		// The call to getCommentByDonationId will make the repository remember which donation the comment belonged to
		$repository->getCommentByDonationId( 1 );
		$repository->updateComment( $comment );

		$storeDonationCalls = $donationRepository->getStoreDonationCalls();
		$this->assertCount( 1, $storeDonationCalls );
		$this->assertEquals( $donation, $storeDonationCalls[0] );
	}

	/**
	 * This test demonstrates a flaw in the implementation of LegacyCommentRepository,
	 * which uses DonationRepository internally, leading to the requirement that the original Donation comment
	 * must be mutated and can't be immutably replaced with a new comment.
	 *
	 * A "proper" implementation of the CommentRepository SHOULD allow for switching out comments.
	 */
	public function testUpdateCommentFailsIfTheCommentIsNew(): void {
		$donation = ValidDonation::newBookedCreditCardDonation();
		$comment = ValidDonation::newPublicComment();
		$donation->addComment( $comment );
		$donationRepository = new DonationRepositorySpy( $donation );
		$repository = new LegacyCommentRepository( $donationRepository );
		// The call to getCommentByDonationId will make the repository remember which donation the comment belonged to
		$repository->getCommentByDonationId( 1 );
		$newComment = clone $comment;
		$newComment->retract();

		$this->expectException( LegacyException::class );

		$repository->updateComment( $newComment );
	}

	public function testUpdateCommentFailsIfLoadedDonationHadNoComment(): void {
		$donation = ValidDonation::newBookedCreditCardDonation();
		$repository = new LegacyCommentRepository( new FakeDonationRepository( $donation ) );
		$repository->getCommentByDonationId( 1 );

		$this->expectException( LegacyException::class );
		$repository->updateComment( ValidDonation::newPublicComment() );
	}

}
