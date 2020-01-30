<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddComment;

use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingDonationRepository;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentUseCase;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentValidator;
use WMDE\FunValidators\Validators\TextPolicyValidator;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentUseCase
 *
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddCommentUseCaseTest extends \PHPUnit\Framework\TestCase {

	private const DONATION_ID = 9001;
	private const COMMENT_TEXT = 'Your programmers deserve a raise';
	private const COMMENT_IS_PUBLIC = true;

	private $donationRepository;
	private $authorizer;
	private $textPolicyValidator;
	private $commentValidator;

	public function setUp(): void {
		$this->donationRepository = new FakeDonationRepository();
		$this->authorizer = new SucceedingDonationAuthorizer();
		$this->textPolicyValidator = $this->newSucceedingTextPolicyValidator();
		$this->commentValidator = $this->newSucceedingAddCommentValidator();
	}

	private function newSucceedingTextPolicyValidator(): TextPolicyValidator {
		return $this->newStubTextPolicyValidator( true );
	}

	private function newStubTextPolicyValidator( bool $returnValue ): TextPolicyValidator {
		$validator = $this->createMock( TextPolicyValidator::class );

		$validator->expects( $this->any() )->method( 'textIsHarmless' )->willReturn( $returnValue );

		return $validator;
	}

	public function testGivenValidRequest_commentGetsAdded(): void {
		$this->donationRepository = $this->newFakeRepositoryWithDonation();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );

		$this->assertEquals(
			new DonationComment(
				self::COMMENT_TEXT,
				self::COMMENT_IS_PUBLIC,
				'nyan Jeroen De Dauw'
			),
			$this->donationRepository->getDonationById( self::DONATION_ID )->getComment()
		);

		$this->assertTrue( $response->isSuccessful() );
	}

	private function newFakeRepositoryWithDonation(): FakeDonationRepository {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( self::DONATION_ID );

		return new FakeDonationRepository( $donation );
	}

	private function newFakeRepositoryWithAnonDonation(): FakeDonationRepository {
		$donation = ValidDonation::newBookedAnonymousPayPalDonation();
		$donation->assignId( self::DONATION_ID );

		return new FakeDonationRepository( $donation );
	}

	private function newUseCase(): AddCommentUseCase {
		return new AddCommentUseCase(
			$this->donationRepository,
			$this->authorizer,
			$this->textPolicyValidator,
			$this->commentValidator
		);
	}

	private function newValidRequest(): AddCommentRequest {
		$addCommentRequest = new AddCommentRequest();

		$addCommentRequest->setCommentText( self::COMMENT_TEXT );
		$addCommentRequest->setIsPublic( self::COMMENT_IS_PUBLIC );
		$addCommentRequest->setIsNamed();
		$addCommentRequest->setDonationId( self::DONATION_ID );

		return $addCommentRequest->freeze()->assertNoNullFields();
	}

	public function testWhenRepositoryThrowsExceptionOnGet_failureResponseIsReturned(): void {
		$this->donationRepository = new ThrowingDonationRepository();
		$this->donationRepository->throwOnGetDonationById();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );

		$this->assertFalse( $response->isSuccessful() );
	}

	public function testWhenRepositoryThrowsExceptionOnStore_failureResponseIsReturned(): void {
		$this->donationRepository = new ThrowingDonationRepository();
		$this->donationRepository->throwOnStoreDonation();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );

		$this->assertFalse( $response->isSuccessful() );
	}

	public function testAuthorizationFails_failureResponseIsReturned(): void {
		$this->authorizer = new FailingDonationAuthorizer();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );

		$this->assertFalse( $response->isSuccessful() );
	}

	public function testWhenDonationDoesNotExist_failureResponseIsReturned(): void {
		$this->assertFalse( $this->newUseCase()->addComment( $this->newValidRequest() )->isSuccessful() );
	}

	public function testWhenTextValidationFails_commentIsMadePrivate(): void {
		$this->donationRepository = $this->newFakeRepositoryWithDonation();
		$this->textPolicyValidator = $this->newFailingTextPolicyValidator();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );
		$this->assertTrue( $response->isSuccessful() );

		$this->assertFalse(
			$this->donationRepository->getDonationById( self::DONATION_ID )->getComment()->isPublic()
		);
	}

	private function newFailingTextPolicyValidator(): TextPolicyValidator {
		return $this->newStubTextPolicyValidator( false );
	}

	public function testWhenTextValidationFails_donationIsMarkedForModeration(): void {
		$this->donationRepository = $this->newFakeRepositoryWithDonation();
		$this->textPolicyValidator = $this->newFailingTextPolicyValidator();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );
		$this->assertTrue( $response->isSuccessful() );

		$this->assertTrue(
			$this->donationRepository->getDonationById( self::DONATION_ID )->needsModeration()
		);
	}

	public function testWhenTextValidationFails_responseMessageDoesNotContainOK(): void {
		$this->donationRepository = $this->newFakeRepositoryWithDonation();
		$this->textPolicyValidator = $this->newFailingTextPolicyValidator();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );
		$this->assertTrue( $response->isSuccessful() );

		$this->assertStringNotContainsString( 'ok', $response->getSuccessMessage() );
	}

	public function testWhenDonationIsMarkedForModeration_responseMessageDoesNotContainOK(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( self::DONATION_ID );
		$donation->markForModeration();

		$this->donationRepository = new FakeDonationRepository( $donation );
		$this->textPolicyValidator = $this->newFailingTextPolicyValidator();

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );
		$this->assertTrue( $response->isSuccessful() );

		$this->assertStringNotContainsString( 'ok', $response->getSuccessMessage() );
	}

	public function testWhenValidationFails_failureResponseIsReturned(): void {
		$this->donationRepository = $this->newFakeRepositoryWithDonation();
		$this->commentValidator = $this->createMock( AddCommentValidator::class );
		$this->commentValidator->method( 'validate' )->willReturn(
			new AddCommentValidationResult(
				[
					'comment' => 'failed'
				]
			)
		);

		$response = $this->newUseCase()->addComment( $this->newValidRequest() );
		$this->assertFalse( $response->isSuccessful() );
	}

	private function newSucceedingAddCommentValidator(): AddCommentValidator {
		$validator = $this->createMock( AddCommentValidator::class );
		$validator->method( 'validate' )->willReturn( new AddCommentValidationResult( [] ) );
		return $validator;
	}

	public function testGivenAnonymousRequest_authorDisplayNameIsAnonymous(): void {
		$this->donationRepository = $this->newFakeRepositoryWithDonation();

		$addCommentRequest = new AddCommentRequest();

		$addCommentRequest->setIsAnonymous();
		$addCommentRequest->setCommentText( self::COMMENT_TEXT );
		$addCommentRequest->setIsPublic( self::COMMENT_IS_PUBLIC );
		$addCommentRequest->setDonationId( self::DONATION_ID );

		$response = $this->newUseCase()->addComment( $addCommentRequest );

		$this->assertSame(
			'Anonym',
			$this->donationRepository->getDonationById( self::DONATION_ID )->getComment()->getAuthorDisplayName()
		);

		$this->assertTrue( $response->isSuccessful() );
	}

	public function testGivenMaliciousAnonymousRequest_authorDisplayNameIsAnonymous(): void {
		$this->donationRepository = $this->newFakeRepositoryWithAnonDonation();

		$addCommentRequest = new AddCommentRequest();

		// Request is set to be named but donation is actually anonymous
		$addCommentRequest->setIsNamed();
		$addCommentRequest->setCommentText( self::COMMENT_TEXT );
		$addCommentRequest->setIsPublic( self::COMMENT_IS_PUBLIC );
		$addCommentRequest->setDonationId( self::DONATION_ID );

		$response = $this->newUseCase()->addComment( $addCommentRequest );

		$this->assertSame(
			'Anonym',
			$this->donationRepository->getDonationById( self::DONATION_ID )->getComment()->getAuthorDisplayName()
		);

		$this->assertTrue( $response->isSuccessful() );
	}

}
