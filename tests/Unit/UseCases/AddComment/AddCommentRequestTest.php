<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddComment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentRequest;

#[CoversClass( AddCommentRequest::class )]
class AddCommentRequestTest extends TestCase {
	private const DONATION_ID = 1234567;

	public function testFieldMutation(): void {
		$text = 'Wikipedia helped me to find proof, but i couldn\'t write it in the margin';

		$request = new AddCommentRequest(
			commentText: $text,
			isPublic: true,
			isAnonymous: false,
			donationId: self::DONATION_ID
		);

		$anonymousRequest = new AddCommentRequest(
			commentText: $text,
			isPublic: true,
			isAnonymous: true,
			donationId: self::DONATION_ID
		);

		$this->assertSame( $text, $request->commentText );
		$this->assertSame( self::DONATION_ID, $request->donationId );
		$this->assertFalse( $request->isAnonymous );
		$this->assertTrue( $request->isPublic );
		$this->assertTrue( $anonymousRequest->isAnonymous );
	}

	public function testCommentIsTrimmed(): void {
		$request = new AddCommentRequest(
			commentText: "    \n\n\nWikipedia\nis\nmy\nfitness\ncoach!\n      ",
			isPublic: true,
			isAnonymous: false,
			donationId: self::DONATION_ID
		);

		$this->assertSame( "Wikipedia\nis\nmy\nfitness\ncoach!", $request->commentText );
	}

}
