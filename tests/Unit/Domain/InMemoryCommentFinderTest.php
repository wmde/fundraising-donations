<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\ReadModel\Comment;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\InMemoryCommentFinder;

#[CoversClass( InMemoryCommentFinder::class )]
class InMemoryCommentFinderTest extends TestCase {

	public function testWhenThereAreNoComments_getCommentsReturnsEmptyArray(): void {
		$this->assertSame( [], ( new InMemoryCommentFinder() )->getPublicComments( 10 ) );
	}

	public function testWhenThereAreComments_getCommentsReturnsThem(): void {
		$this->assertEquals(
			[
				$this->newComment( 'name0' ),
				$this->newComment( 'name1' ),
				$this->newComment( 'name2' )
			],
			( new InMemoryCommentFinder(
				$this->newComment( 'name0' ),
				$this->newComment( 'name1' ),
				$this->newComment( 'name2' )
			) )->getPublicComments( 10 )
		);
	}

	public function testGivenLimitSmallerThanCommentCount_getCommentsLimitsItsResult(): void {
		$this->assertEquals(
			[
				$this->newComment( 'name0' ),
				$this->newComment( 'name1' )
			],
			( new InMemoryCommentFinder(
				$this->newComment( 'name0' ),
				$this->newComment( 'name1' ),
				$this->newComment( 'name2' )
			) )->getPublicComments( 2 )
		);
	}

	private function newComment( string $name ): Comment {
		return new Comment(
			authorName: $name,
			donationAmount: 42,
			commentText: 'comment',
			donationTime: new \DateTime( '1984-01-01' ),
			donationId: 0
		);
	}

}
