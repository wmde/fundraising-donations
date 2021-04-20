<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\ModerateComment;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
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

	public function testWhenDonationIsNotFound_moderationFails(): void {
		$repository = new FakeDonationRepository();
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( 1, self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentErrorResponse::class, $response );
		$this->assertSame( ModerateCommentErrorResponse::ERROR_DONATION_NOT_FOUND, $response->getError() );
	}

	public function testWhenDonationHasNoComment_moderationFails(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$repository = new FakeDonationRepository( $donation );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( $donation->getId(), self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentErrorResponse::class, $response );
		$this->assertSame( ModerateCommentErrorResponse::ERROR_DONATION_HAS_NO_COMMENT, $response->getError() );
	}

	/**
	 * @dataProvider commentProviderForPublication
	 */
	public function testWhenDonationHasComment_publicationSucceeds( DonationComment $comment, string $assertMessage ): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( $comment );
		$repository = new FakeDonationRepository( $donation );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( $donation->getId(), self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentSuccessResponse::class, $response );
		$this->assertSame( $donation->getId(), $response->getDonationId() );
		$this->assertTrue( $donation->getComment()->isPublic(), $assertMessage );
	}

	public function commentProviderForPublication(): iterable {
		yield [ $this->newPrivateComment(), 'private comments should be published' ];
		yield [ $this->newPublicComment(), 'public comments should stay published' ];
	}

	public function testWhenPublicationSucceeds_donationGetsPersisted(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( $this->newPrivateComment() );
		$repository = new DonationRepositorySpy( $donation );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::publishComment( $donation->getId(), self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentSuccessResponse::class, $response );
		$storedDonations = $repository->getStoreDonationCalls();
		$this->assertCount( 1, $storedDonations );
		$this->assertSame( $donation->getId(), $storedDonations[0]->getId() );
	}

	/**
	 * @dataProvider commentProviderForRetraction
	 */
	public function testWhenDonationHasComment_retractionSucceeds( DonationComment $comment, string $assertMessage ): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( $comment );
		$repository = new FakeDonationRepository( $donation );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::retractComment( $donation->getId(), self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentSuccessResponse::class, $response );
		$this->assertSame( $donation->getId(), $response->getDonationId() );
		$this->assertFalse( $donation->getComment()->isPublic(), $assertMessage );
	}

	public function commentProviderForRetraction(): iterable {
		yield [ $this->newPrivateComment(), 'private comments should stay private' ];
		yield [ $this->newPublicComment(), 'public comments should be retracted' ];
	}

	public function testWhenRetractionSucceeds_donationGetsPersisted(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( $this->newPublicComment() );
		$repository = new DonationRepositorySpy( $donation );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$request = ModerateCommentRequest::retractComment( $donation->getId(), self::AUTHORIZED_USER_NAME );

		$response = $useCase->moderateComment( $request );

		$this->assertInstanceOf( ModerateCommentSuccessResponse::class, $response );
		$storedDonations = $repository->getStoreDonationCalls();
		$this->assertCount( 1, $storedDonations );
		$this->assertSame( $donation->getId(), $storedDonations[0]->getId() );
	}

	public function testPublicationAndRetractionLogAdminUserName(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( $this->newPublicComment() );
		$repository = new DonationRepositorySpy( $donation );
		$logger = new DonationEventLoggerSpy();
		$useCase = new ModerateCommentUseCase( $repository, $logger );
		$donationId = $donation->getId();
		$retractionRequest = ModerateCommentRequest::retractComment( $donationId, self::AUTHORIZED_USER_NAME );
		$publicationRequest = ModerateCommentRequest::publishComment( $donationId, 'OliverTheOverrider' );

		$useCase->moderateComment( $retractionRequest );
		$useCase->moderateComment( $publicationRequest );

		$logCalls = $logger->getLogCalls();
		$this->assertCount( 2, $logCalls );
		$expectedLogCalls = [
			[ $donationId, 'Comment set to private by user: MarkusTheModerator' ],
			[ $donationId, 'Comment published by user: OliverTheOverrider' ],
		];
		$this->assertEquals( $expectedLogCalls, $logCalls );
	}

	private function newPublicComment(): DonationComment {
		return new DonationComment( 'I love Wikipedia', true, 'Donnie Donor' );
	}

	private function newPrivateComment(): DonationComment {
		return new DonationComment( 'I donated for Wikipedia and all I got is this confirmation T-Shirt', false, 'anonymous' );
	}
}
