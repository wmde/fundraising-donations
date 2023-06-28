<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\ModerateComment;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentErrorResponse;
use WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentRequest;
use WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentSuccessResponse;
use WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentRequest
 * @covers \WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentSuccessResponse
 * @covers \WMDE\Fundraising\DonationContext\UseCases\ModerateComment\ModerateCommentErrorResponse
 */
class ModerateCommentUseCaseTest extends TestCase {

	private const AUTHORIZED_USER_NAME = 'MarkusTheModerator';
	private const MISSING_DONATION_ID = 123;
	private const DONATION_ID = 7;

	public function testWhenDonationIsNotFound_moderationFails(): void {
		$repository = $this->createMock( CommentRepository::class );
		$repository->method( 'getCommentByDonationId' )->willThrowException( new GetDonationException() );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( self::MISSING_DONATION_ID, self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentErrorResponse::class, $response );
		$this->assertSame( ModerateCommentErrorResponse::ERROR_DONATION_NOT_FOUND, $response->getError() );
	}

	public function testWhenDonationHasNoComment_moderationFails(): void {
		$repository = $this->createMock( CommentRepository::class );
		$repository->method( 'getCommentByDonationId' )->willReturn( null );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( self::DONATION_ID, self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentErrorResponse::class, $response );
		$this->assertSame( ModerateCommentErrorResponse::ERROR_DONATION_HAS_NO_COMMENT, $response->getError() );
	}

	/**
	 * @dataProvider commentProviderForPublication
	 */
	public function testWhenDonationHasComment_publicationSucceeds( DonationComment $comment, string $assertMessage ): void {
		$repository = $this->createMock( CommentRepository::class );
		$repository->method( 'getCommentByDonationId' )->willReturn( $comment );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( self::DONATION_ID, self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentSuccessResponse::class, $response );
		$this->assertSame( self::DONATION_ID, $response->getDonationId() );
		$this->assertTrue( $comment->isPublic(), $assertMessage );
	}

	/**
	 * @return iterable<array{DonationComment, string}>
	 */
	public static function commentProviderForPublication(): iterable {
		yield [ self::newPrivateComment(), 'private comments should be published' ];
		yield [ self::newPublicComment(), 'public comments should stay published' ];
	}

	public function testWhenPublicationSucceeds_donationGetsPersisted(): void {
		$comment = $this->newPrivateComment();
		$repository = $this->createMock( CommentRepository::class );
		$repository->method( 'getCommentByDonationId' )->willReturn( $comment );
		$repository->expects( $this->once() )->method( 'updateComment' )->with( $comment );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( self::DONATION_ID, self::AUTHORIZED_USER_NAME );

		$useCase->moderateComment( $request );
	}

	/**
	 * @dataProvider commentProviderForRetraction
	 */
	public function testWhenDonationHasComment_retractionSucceeds( DonationComment $comment, string $assertMessage ): void {
		$repository = $this->createMock( CommentRepository::class );
		$repository->method( 'getCommentByDonationId' )->willReturn( $comment );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::retractComment( self::DONATION_ID, self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentSuccessResponse::class, $response );
		$this->assertSame( self::DONATION_ID, $response->getDonationId() );
		$this->assertFalse( $comment->isPublic(), $assertMessage );
	}

	/**
	 * @return iterable<array{DonationComment, string}>
	 */
	public static function commentProviderForRetraction(): iterable {
		yield [ self::newPrivateComment(), 'private comments should stay private' ];
		yield [ self::newPublicComment(), 'public comments should be retracted' ];
	}

	public function testWhenRetractionSucceeds_donationGetsPersisted(): void {
		$comment = self::newPublicComment();
		$repository = $this->createMock( CommentRepository::class );
		$repository->method( 'getCommentByDonationId' )->willReturn( $comment );
		$repository->expects( $this->once() )->method( 'updateComment' )->with( $comment );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::retractComment( self::DONATION_ID, self::AUTHORIZED_USER_NAME );

		$useCase->moderateComment( $request );
	}

	public function testPublicationAndRetractionLogAdminUserName(): void {
		$repository = $this->createMock( CommentRepository::class );
		$repository->method( 'getCommentByDonationId' )->willReturn( self::newPublicComment() );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$retractionRequest = ModerateCommentRequest::retractComment( self::DONATION_ID, self::AUTHORIZED_USER_NAME );
		$publicationRequest = ModerateCommentRequest::publishComment( self::DONATION_ID, 'OliverTheOverrider' );

		$useCase->moderateComment( $retractionRequest );
		$useCase->moderateComment( $publicationRequest );

		$logCalls = $logger->getLogCalls();
		$this->assertCount( 2, $logCalls );
		$expectedLogCalls = [
			[ self::DONATION_ID, 'Comment set to private by user: MarkusTheModerator' ],
			[ self::DONATION_ID, 'Comment published by user: OliverTheOverrider' ],
		];
		$this->assertEquals( $expectedLogCalls, $logCalls );
	}

	private static function newPublicComment(): DonationComment {
		return new DonationComment( 'I love Wikipedia', true, 'Donnie Donor' );
	}

	private static function newPrivateComment(): DonationComment {
		return new DonationComment( 'I donated for Wikipedia and all I got is this confirmation T-Shirt', false, 'anonymous' );
	}
}
