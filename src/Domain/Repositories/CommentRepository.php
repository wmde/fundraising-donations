<?php

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;

interface CommentRepository {
	public function getCommentById( int $commentId ): ?DonationComment;

	/**
	 * @param int $donationId
	 *
	 * @return DonationComment|null
	 * @throws GetDonationException
	 * @deprecated Use getCommentById when https://phabricator.wikimedia.org/T203679 is done
	 */
	public function getCommentByDonationId( int $donationId ): ?DonationComment;

	/**
	 * @param int $donationId
	 * @param DonationComment $comment
	 *
	 * @return int Comment Id
	 * @throws GetDonationException
	 */
	public function insertCommentForDonation( int $donationId, DonationComment $comment ): int;

	public function updateComment( DonationComment $comment ): void;
}
