<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;

/**
 * This class is an implementation of the CommentRepository using the legacy
 * database schema where comments are part of the donation table.
 *
 * This class should be replaced with an implementation that's more independent
 * from the DonationRepository. See https://phabricator.wikimedia.org/T203679
 */
class LegacyCommentRepository implements CommentRepository {

	private array $fetchedComments = [];
	private DonationRepository $donationRepository;

	public function __construct( DonationRepository $donationRepository ) {
 $this->donationRepository = $donationRepository;
	}

	public function getCommentById( int $commentId ): ?DonationComment {
		throw new LegacyException( 'getCommentById not implementable with legacy data base schema' );
	}

	public function getCommentByDonationId( int $donationId ): ?DonationComment {
		$donation = $this->donationRepository->getDonationById( $donationId );
		if ( $donation === null ) {
			throw new GetDonationException();
		}
		$comment = $donation->getComment();
		if ( $comment !== null ) {
			$this->fetchedComments[spl_object_id( $comment )] = $donation->getId();
		}
		return $comment;
	}

	public function insertCommentForDonation( int $donationId, DonationComment $comment ): int {
		$donation = $this->donationRepository->getDonationById( $donationId );
		if ( $donation === null ) {
			throw new GetDonationException();
		}
		$donation->addComment( $comment );
		$this->donationRepository->storeDonation( $donation );
		// We fake the comment ID by returning the donation id. The calling code must NOT rely on that!
		return intval( $donation->getId() );
	}

	public function updateComment( DonationComment $comment ): void {
		$objectId = spl_object_id( $comment );
		if ( !isset( $this->fetchedComments[$objectId] ) ) {
			throw new LegacyException( 'updateComment is not implementable without calling getCommentByDonationId first. Please check your call order and make sure you\'re only updating donations that previously had a comment.' );
		}
	}

}
