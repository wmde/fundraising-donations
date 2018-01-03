<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Unit\UseCases\AddComment;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment\AddCommentRequest;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment\AddCommentValidator;

/**
 * @covers \WMDE\Fundraising\Frontend\DonationContext\UseCases\AddComment\AddCommentValidator
 */
class AddCommentValidatorTest extends TestCase {

	private function newValidAddCommentRequest(): AddCommentRequest {
		$request = new AddCommentRequest();
		$request->setIsNamed();
		$request->setCommentText(
			'In the common tongue it reads "One Wiki to Rule Them All. One Wiki to Find Them. ' .
			'One Wiki to Bring Them All and In The Darkness Bind Them." '
		);
		$request->setIsPublic( true );
		$request->setDonationId( 1 );
		return $request;
	}

	public function testValidCommentRequest_isSuccessful(): void {
		$validator = new AddCommentValidator();
		$this->assertTrue( $validator->validate( $this->newValidAddCommentRequest() )->isSuccessful() );
	}

	public function testLongComment_isNotSuccessful(): void {
		$validator = new AddCommentValidator();
		$request = $this->newValidAddCommentRequest();
		$request->setCommentText( str_repeat( 'All work and no play makes jack a dull boy.', 1000 ) );
		$this->assertFalse( $validator->validate( $request )->isSuccessful() );
	}

}
