<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess\PaymentMigration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\ChunkedQueryResultIterator;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration\ChunkedQueryResultIterator
 */
class ChunkedQueryResultIteratorTest extends TestCase {
	private Connection $db;

	protected function setUp(): void {
		parent::setUp();
		$dsnParser  = new DsnParser( [ 'sqlite' => 'pdo_sqlite' ] );
		$this->db = DriverManager::getConnection(
			$dsnParser->parse( 'sqlite:///:memory:' )
		);
		$this->db->executeQuery( "CREATE TABLE test( id INTEGER PRIMARY KEY )" );
	}

	protected function tearDown(): void {
		parent::tearDown();
		$this->db->executeQuery( "DROP TABLE test" );
		$this->db->close();
	}

	/**
	 * @dataProvider provideNumRowsAndChunkSizes
	 */
	public function testIterateChunks( int $numRows, int $chunkSize ): void {
		$this->insertRows( $numRows );
		$qb = $this->db->createQueryBuilder();
		$qb->select( 'id' )
			->from( 'test' );

		$iterator = new ChunkedQueryResultIterator( $qb, 'id', $chunkSize, $numRows );

		$result = iterator_to_array( $iterator );
		$lastResult = end( $result );

		$this->assertCount( $numRows, $result );
		$this->assertSame( [ 'id' => $numRows ], $lastResult );
	}

	/**
	 * @return iterable<array{int,int}>
	 */
	public static function provideNumRowsAndChunkSizes(): iterable {
		yield 'chunk size 1 - query for each row' => [ 20, 1 ];
		yield 'chunk size 2' => [ 40, 2 ];
		yield 'chunk size 5, only 2 queries needed' => [ 10, 5 ];
		yield 'chunk size 5, 2 queries needed' => [ 9, 5 ];
		yield 'chunk size 5 with 11 rows - 3 queries needed' => [ 11, 5 ];
		yield 'chunk equal to row size' => [ 11, 11 ];
		yield 'chunk bigger than row size' => [ 11, 100 ];
	}

	/**
	 * @dataProvider provideChunkSizes
	 */
	public function testMaxOffsetLimitsReturnedRows( int $chunkSize ): void {
		$maxOffset = 23;
		$this->insertRows( 1000 );
		$qb = $this->db->createQueryBuilder();
		$qb->select( 'id' )
			->from( 'test' );

		$iterator = new ChunkedQueryResultIterator( $qb, 'id', $chunkSize, $maxOffset );

		$result = iterator_to_array( $iterator );
		$lastResult = end( $result );

		$this->assertCount( $maxOffset, $result );
		$this->assertSame( [ 'id' => $maxOffset ], $lastResult );
	}

	/**
	 * @return iterable<int[]>
	 */
	public static function provideChunkSizes(): iterable {
		yield [ 1 ];
		yield [ 10 ];
		yield [ 19 ];
		yield [ 22 ];
		yield [ 23 ];
		yield [ 24 ];
		yield [ 1000 ];
		yield [ 1001 ];
	}

	/**
	 * @dataProvider provideChunkSizes
	 */
	public function testMaxOffsetAndStartOffsetLimitReturnedRows( int $chunkSize ): void {
		$maxOffset = 42;
		$startOffset = 23;
		$this->insertRows( 1000 );
		$qb = $this->db->createQueryBuilder();
		$qb->select( 'id' )
			->from( 'test' );

		$iterator = new ChunkedQueryResultIterator( $qb, 'id', $chunkSize, $maxOffset, $startOffset );

		$result = iterator_to_array( $iterator );
		$lastResult = end( $result );

		$this->assertCount( 19, $result );
		$this->assertSame( [ 'id' => 24 ], $result[0] );
		$this->assertSame( [ 'id' => 42 ], $lastResult );
	}

	public function testIteratorEnforcesSelectQueries(): void {
		$qb = $this->db->createQueryBuilder();
		$qb->insert( 'test' )
			->values( [ 'id' => 1 ] );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessageMatches( '/iterating select/i' );

		new ChunkedQueryResultIterator( $qb, 'id', 100, 100 );
	}

	private function insertRows( int $numRows ): void {
		$this->db->beginTransaction();
		for ( $i = 0; $i < $numRows;$i++ ) {
			$this->db->insert( 'test', [ 'id' => $i + 1 ] );
		}
		$this->db->commit();
	}

}
