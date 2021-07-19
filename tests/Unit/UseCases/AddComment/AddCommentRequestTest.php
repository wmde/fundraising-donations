<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddComment;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentRequest;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddComment\AddCommentRequest
 */
class AddCommentRequestTest extends TestCase {
	private const DONATION_ID = 1234567;

	public function testFieldMutation() {
		$text = 'Wikipedia helped me to find proof, but i couldn\'t write it in the margin';
		$request = new AddCommentRequest();
		$request->setCommentText( $text );
		$request->setIsNamed();
		$request->setDonationId( self::DONATION_ID );
		$request->setIsPublic( true );
		$anonymousRequest = new AddCommentRequest();
		$anonymousRequest->setIsAnonymous();

		$this->assertSame( $text, $request->getCommentText() );
		$this->assertSame( self::DONATION_ID, $request->getDonationId() );
		$this->assertFalse( $request->isAnonymous() );
		$this->assertTrue( $request->isPublic() );
		$this->assertTrue( $anonymousRequest->isAnonymous() );
	}

	public function testCommentIsTrimmed(): void {
		$request = new AddCommentRequest();
		$request->setCommentText( "    \n\n\nWikipedia\nis\nmy\nfitness\ncoach!\n      " );

		$this->assertSame( "Wikipedia\nis\nmy\nfitness\ncoach!", $request->getCommentText() );
	}

}
