<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\ListComments;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\ReadModel\Comment;
use WMDE\Fundraising\DonationContext\UseCases\ListComments\CommentList;

/**
 * @covers WMDE\Fundraising\DonationContext\UseCases\ListComments\CommentList
 */
class CommentListTest extends TestCase {

	public function testGivenNoArguments_constructorCreatesEmptyList(): void {
		$this->assertSame( [], ( new CommentList() )->toArray() );
	}

	public function testGivenOneComment_constructorCreatesListWithComment(): void {
		$comment = new Comment(
			authorName: 'name0',
			donationAmount: 42,
			commentText: 'comment',
			donationTime: new \DateTime( '1984-01-01' ),
			donationId: 0
		);

		$this->assertSame( [ $comment ], ( new CommentList( $comment ) )->toArray() );
	}

	public function testGivenMultipleComments_constructorCreatesListWithAllComments(): void {
		$comment0 = new Comment(
			authorName: 'name0',
			donationAmount: 42,
			commentText: 'comment',
			donationTime: new \DateTime( '1984-01-01' ),
			donationId: 0
		);

		$comment1 = new Comment(
			authorName: 'name1',
			donationAmount: 42,
			commentText: 'comment',
			donationTime: new \DateTime( '1984-01-01' ),
			donationId: 0
		);

		$comment2 = new Comment(
			authorName: 'name2',
			donationAmount: 42,
			commentText: 'comment',
			donationTime: new \DateTime( '1984-01-01' ),
			donationId: 0
		);

		$this->assertSame(
			[ $comment0, $comment1, $comment2 ],
			( new CommentList( $comment0, $comment1, $comment2 ) )->toArray()
		);
	}

}
