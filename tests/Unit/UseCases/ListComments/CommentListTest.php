<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\ListComments;

use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentWithAmount;
use WMDE\Fundraising\DonationContext\UseCases\ListComments\CommentList;

/**
 * @covers WMDE\Fundraising\DonationContext\UseCases\ListComments\CommentList
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CommentListTest extends \PHPUnit\Framework\TestCase {

	public function testGivenNoArguments_constructorCreatesEmptyList(): void {
		$this->assertSame( [], ( new CommentList() )->toArray() );
	}

	public function testGivenOneComment_constructorCreatesListWithComment(): void {
		$comment = CommentWithAmount::newInstance()
			->setAuthorName( 'name0' )
			->setCommentText( 'comment' )
			->setDonationAmount( 42 )
			->setDonationTime( new \DateTime( '1984-01-01' ) );

		$this->assertSame( [ $comment ], ( new CommentList( $comment ) )->toArray() );
	}

	public function testGivenMultipleComments_constructorCreatesListWithAllComments(): void {
		$comment0 = CommentWithAmount::newInstance()
			->setAuthorName( 'name0' )
			->setCommentText( 'comment' )
			->setDonationAmount( 42 )
			->setDonationTime( new \DateTime( '1984-01-01' ) );

		$comment1 = CommentWithAmount::newInstance()
			->setAuthorName( 'name1' )
			->setCommentText( 'comment' )
			->setDonationAmount( 42 )
			->setDonationTime( new \DateTime( '1984-01-01' ) );

		$comment2 = CommentWithAmount::newInstance()
			->setAuthorName( 'name2' )
			->setCommentText( 'comment' )
			->setDonationAmount( 42 )
			->setDonationTime( new \DateTime( '1984-01-01' ) );

		$this->assertSame(
			[ $comment0, $comment1, $comment2 ],
			( new CommentList( $comment0, $comment1, $comment2 ) )->toArray()
		);
	}

}
