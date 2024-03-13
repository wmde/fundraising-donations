<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddComment;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentValidator;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentValidator
 */
class AddCommentValidatorTest extends TestCase {

	private function newAddCommentRequest( string $text ): AddCommentRequest {
		return new AddCommentRequest(
			commentText: $text,
			isPublic: true,
			isAnonymous: false,
			donationId: 1
		);
	}

	/**
	 * @dataProvider getCreativeButValidCommentTexts
	 */
	public function testValidCommentRequest_isSuccessful( string $text ): void {
		$validator = new AddCommentValidator();
		$this->assertTrue( $validator->validate( $this->newAddCommentRequest( $text ) )->isSuccessful() );
	}

	/**
	 * @return iterable<array{string}>
	 */
	public static function getCreativeButValidCommentTexts(): iterable {
		yield [ 'In the common tongue it reads "One Wiki to Rule Them All. One Wiki to Find Them. ' .
			'One Wiki to Bring Them All and In The Darkness Bind Them." ' ];
		yield [ 'Greetings from China ア' ];
		yield [ 'Grüzi aus der Schweiz' ];
		yield [ 'Österreichisches Servus!' ];
		yield [ '¡Hola de España!' ];
	}

	public function testLongComment_isNotSuccessful(): void {
		$validator = new AddCommentValidator();
		$request = $this->newAddCommentRequest( str_repeat( 'All work and no play makes jack a dull boy.', 1000 ) );
		$validationResult = $validator->validate( $request );
		$this->assertFalse( $validationResult->isSuccessful() );
		$this->assertSame( 'comment_failure_text_too_long', $validationResult->getFirstViolation() );
	}

	public function testCommentWithInvalidCharacters_isNotSuccessful(): void {
		$validator = new AddCommentValidator();
		$request = $this->newAddCommentRequest( 'Gotta make dat 💲💲💲💲💴💰💳' );
		$validationResult = $validator->validate( $request );
		$this->assertFalse( $validationResult->isSuccessful() );
		$this->assertSame( 'comment_failure_text_invalid_chars', $validationResult->getFirstViolation() );
	}

}
