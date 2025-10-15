<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationTracking;
use WMDE\Fundraising\DonationContext\Domain\DonationTrackingFetcher;

class DatabaseDonationTrackingFetcher implements DonationTrackingFetcher {

	/** @var array<string, int> */
	private array $trackingIdCache = [];

	/** @var Query<null, DonationTracking> */
	private Query $findTrackingQuery;

	public function __construct( private readonly EntityManager $entityManager ) {
		$qb = $this->entityManager->createQueryBuilder();
		$qb->select( 't' )
			->from( DonationTracking::class, 't' )
			->where( 't.campaign = :campaign' )
			->andWhere( 't.keyword = :keyword' );
		/** @var Query<null, DonationTracking> $query */
		$query = $qb->getQuery();
		$this->findTrackingQuery = $query;
	}

	public function getTrackingId( string $campaign, string $keyword ): int {
		$campaign = strtolower( $campaign );
		$keyword = strtolower( $keyword );
		$trackingKey = $campaign . '/' . $keyword;
		if ( isset( $this->trackingIdCache[ $trackingKey ] ) ) {
			return $this->trackingIdCache[ $trackingKey ];
		}

		$conn = $this->getConnection();
		$findQuery = $this->getFindQuery( $conn );
		$findQuery->bindValue( 1, $campaign, Types::STRING );
		$findQuery->bindValue( 2, $keyword, Types::STRING );

		/** @var array{id:string|int}[] $rows */
		$rows = $findQuery->executeQuery()->fetchAllAssociative();

		// When tracking was found, cache ID and return it
		if ( count( $rows ) > 0 ) {
			$id = intval( $rows[0]['id'] );
			$this->trackingIdCache[ $trackingKey ] = $id;
			return $id;
		}

		$insertQuery = $this->getInsertQuery( $conn );
		$insertQuery
			->setParameter( 'campaign', $campaign, Types::STRING )
			->setParameter( 'keyword', $keyword, Types::STRING )
			->executeQuery();

		$id = intval( $conn->lastInsertId() );
		$this->trackingIdCache[ $trackingKey ] = $id;
		return $id;
	}

	public function getTracking( string $campaign, string $keyword ): DonationTracking {
		$campaign = strtolower( $campaign );
		$keyword = strtolower( $keyword );
		$this->findTrackingQuery
			->setParameter( 'campaign', $campaign )
			->setParameter( 'keyword', $keyword );

		/** @var DonationTracking|null $tracking */
		$tracking = $this->findTrackingQuery->getOneOrNullResult();

		if ( $tracking !== null ) {
			return $tracking;
		}

		$tracking = new DonationTracking( $campaign, $keyword );
		$this->entityManager->persist( $tracking );
		$this->entityManager->flush();
		return $tracking;
	}

	/**
	 * This function is protected to be able to override it in tests
	 */
	protected function getConnection(): Connection {
		return $this->entityManager->getConnection();
	}

	private function getFindQuery( Connection $conn ): Statement {
		static $findQuery = null;
		if ( $findQuery === null ) {
			$sql = $this->findTrackingQuery->getSQL();
			// Remove column aliases from Doctrine ORM query
			$sql = preg_replace( '/\sAS\s[^,\s]+/', '', $sql );
			// @phpstan-ignore argument.type (The regex should never fail, thus never be false/null)
			$findQuery = $conn->prepare( $sql );
		}
		return $findQuery;
	}

	private function getInsertQuery( Connection $conn ): QueryBuilder {
		static $insertQuery = null;
		if ( $insertQuery === null ) {
			$insertQuery = $conn->createQueryBuilder()
				->insert( 'donation_tracking' )
				->values( [ 'campaign' => ':campaign', 'keyword' => ':keyword' ] );
		}
		return $insertQuery;
	}
}
