<?php

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DatabaseNotificationLog;
use WMDE\Fundraising\DonationContext\Tests\NotificationLogSchema;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DatabaseNotificationLog
 */
class DatabaseNotificationLogTest extends TestCase {

	private const MISSING_DONATION_ID = 41;
	private const EXISTING_DONATION_ID = 42;

	private Connection $connection;

	public function setUp(): void {
		$this->connection = TestEnvironment::newInstance()->getFactory()->getConnection();
		NotificationLogSchema::createSchema( $this->connection );
		parent::setUp();
	}

	public function testGivenEmptyTable_NoLogIsFound(): void {
		$log = new DatabaseNotificationLog( $this->connection );

		$this->assertFalse( $log->hasSentConfirmationFor( self::MISSING_DONATION_ID ) );
	}

	public function testGivenDonationIdInDatabase_LoggerFindsSentConfirmation(): void {
		$this->connection->insert( 'donation_notification_log', [ 'donation_id' => self::EXISTING_DONATION_ID ] );
		$log = new DatabaseNotificationLog( $this->connection );

		$this->assertTrue( $log->hasSentConfirmationFor( self::EXISTING_DONATION_ID ) );
		$this->assertFalse( $log->hasSentConfirmationFor( self::MISSING_DONATION_ID ) );
	}

	public function testLogConfirmationSentWritesToDatabase(): void {
		$table_name = NotificationLogSchema::TABLE_NAME;
		$log = new DatabaseNotificationLog( $this->connection );
		$log->logConfirmationSent( self::MISSING_DONATION_ID );

		$count = $this->connection->fetchOne(
			"SELECT count(*) as row_count FROM {$table_name} WHERE donation_id = ?",
			[ self::MISSING_DONATION_ID ]
		);

		$this->assertSame( 1, intval( $count ) );
	}

	public function testGivenDonationIdAlreadyLogged_writingConfirmationFails(): void {
		$this->connection->insert( 'donation_notification_log', [ 'donation_id' => self::EXISTING_DONATION_ID ] );
		$table_name = NotificationLogSchema::TABLE_NAME;
		$log = new DatabaseNotificationLog( $this->connection );

		$log->logConfirmationSent( self::EXISTING_DONATION_ID );

		$count = $this->connection->fetchOne(
			"SELECT count(*) as row_count FROM {$table_name} WHERE donation_id = ?",
			[ self::EXISTING_DONATION_ID ]
		);
		$this->assertSame( 1, intval( $count ) );
	}

}
