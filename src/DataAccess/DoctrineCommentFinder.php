<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentFinder;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentListingException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentWithAmount;

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
	 * @see CommentFinder::getPublicComments
	 *
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return CommentWithAmount[]
	 */
	public function getPublicComments( int $limit, int $offset = 0 ): array {
		return array_map(
			static function ( Donation $donation ) {
				return CommentWithAmount::newInstance()
					->setAuthorName( $donation->getPublicRecord() )
					->setCommentText( $donation->getComment() )
					->setDonationAmount( (float)$donation->getAmount() )
					->setDonationTime( $donation->getCreationTime() )
					->setDonationId( $donation->getId() )
					->freeze()
					->assertNoNullFields();
			},
			$this->getDonations( $limit, $offset )
		);
	}

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
