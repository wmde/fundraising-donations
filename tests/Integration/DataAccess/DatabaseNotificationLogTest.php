<?php

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DatabaseNotificationLog;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationNotificationLog;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

#[CoversClass( DatabaseNotificationLog::class )]
class DatabaseNotificationLogTest extends TestCase {

	private const MISSING_DONATION_ID = 41;
	private const EXISTING_DONATION_ID = 42;

	private EntityManager $entityManager;
	private Connection $connection;
	private string $tableName;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->connection = $factory->getConnection();
		$this->entityManager = $factory->getEntityManager();
		$this->tableName = $factory->getEntityManager()->getClassMetadata( DonationNotificationLog::class )->getTableName();
		parent::setUp();
	}

	public function testGivenEmptyTable_NoLogIsFound(): void {
		$log = new DatabaseNotificationLog( $this->entityManager );

		$this->assertFalse( $log->hasSentConfirmationFor( self::MISSING_DONATION_ID ) );
	}

	public function testGivenDonationIdInDatabase_LoggerFindsSentConfirmation(): void {
		$this->insertLogEntry();
		$log = new DatabaseNotificationLog( $this->entityManager );

		$this->assertTrue( $log->hasSentConfirmationFor( self::EXISTING_DONATION_ID ) );
		$this->assertFalse( $log->hasSentConfirmationFor( self::MISSING_DONATION_ID ) );
	}

	public function testLogConfirmationSentWritesToDatabase(): void {
		$log = new DatabaseNotificationLog( $this->entityManager );
		$log->logConfirmationSent( self::MISSING_DONATION_ID );

		/**
		 * @var string $count
		 */
		$count = $this->connection->fetchOne(
			"SELECT count(*) as row_count FROM {$this->tableName} WHERE donation_id = ?",
			[ self::MISSING_DONATION_ID ]
		);

		$this->assertSame( 1, intval( $count ) );
	}

	public function testGivenDonationIdAlreadyLogged_writingConfirmationFails(): void {
		$this->insertLogEntry();
		$log = new DatabaseNotificationLog( $this->entityManager );

		$log->logConfirmationSent( self::EXISTING_DONATION_ID );

		/**
		 * @var string $count
		 */
		$count = $this->connection->fetchOne(
			"SELECT count(*) as row_count FROM {$this->tableName} WHERE donation_id = ?",
			[ self::EXISTING_DONATION_ID ]
		);
		$this->assertSame( 1, intval( $count ) );
	}

	private function insertLogEntry(): void {
		$this->entityManager->persist( new DonationNotificationLog( self::EXISTING_DONATION_ID ) );
		$this->entityManager->flush();
	}

}
