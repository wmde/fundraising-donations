<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Domain\ReadModel\Comment;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentFinder;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentListingException;

/**
 * @todo Use database tables and arrays instead of ORM to avoid deprecated getAmount
 *       See https://phabricator.wikimedia.org/T311061
 */
class DoctrineCommentFinder implements CommentFinder {

	private EntityManager $entityManager;

	public function __construct( EntityManager $entityManager ) {
		$this->entityManager = $entityManager;
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return Comment[]
	 * @see CommentFinder::getPublicComments
	 *
	 */
	public function getPublicComments( int $limit, int $offset = 0 ): array {
		return array_map(
			static function ( Donation $donation ) {
				return new Comment(
					authorName: $donation->getPublicRecord(),
					donationAmount: (float)$donation->getAmount(),
					commentText: $donation->getComment(),
					donationTime: $donation->getCreationTime(),
					donationId: $donation->getId() ?? 0
				);
			},
			$this->getDonations( $limit, $offset )
		);
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return Donation[]
	 */
	private function getDonations( int $limit, int $offset ): array {
		try {
			return $this->entityManager->getRepository( Donation::class )->findBy(
				[
					'isPublic' => true,
					'deletionTime' => null,
					'status' => [ Donation::STATUS_NEW, Donation::STATUS_EXTERNAL_BOOKED ]
				],
				[
					'creationTime' => 'DESC'
				],
				$limit,
				$offset
			);
		} catch ( ORMException $ex ) {
			throw new CommentListingException( $ex );
		}
	}

}
