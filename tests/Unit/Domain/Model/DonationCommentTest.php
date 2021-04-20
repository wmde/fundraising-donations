<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;

/**
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\DonationComment
 */
class DonationCommentTest extends TestCase {
	public function testCreateComment(): void {
		$commentText = 'My heartfelt thanks!';
		$name = 'Donnie Donor';
		$comment = new DonationComment( $commentText, true, $name );

		$this->assertSame( $commentText, $comment->getCommentText() );
		$this->assertTrue( $comment->isPublic() );
		$this->assertSame( $name, $comment->getAuthorDisplayName() );
	}

	public function testCommentCanBePublished(): void {
		$comment = new DonationComment( 'Greetings from Scunthorpe', false, 'Johnny English' );

		$comment->publish();

		$this->assertTrue( $comment->isPublic() );
	}

	public function testCommentCanBeRetracted(): void {
		$comment = new DonationComment(
			'I just got my PhD with content copied from Wikipedia, now it\'s time to give back',
			true,
			'Dr. Paul Politician'
		);

		$comment->retract();

		$this->assertFalse( $comment->isPublic() );
	}

}
