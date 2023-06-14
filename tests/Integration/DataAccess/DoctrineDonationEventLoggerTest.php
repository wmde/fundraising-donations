<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationEventLogger;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogException;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

/**
 * @covers WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationEventLogger
 */
class DoctrineDonationEventLoggerTest extends \PHPUnit\Framework\TestCase {

	private const DEFAULT_MESSAGE = 'Log message';
	private const LOG_TIMESTAMP = '2015-10-21 21:00:04';
	private const DUMMY_PAYMENT_ID = 42;
	private const DONATION_ID = 1;

	private EntityManager $entityManager;

	public function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
	}

	public function testIfDonationDoesNotExistLoggingFails(): void {
		$logger = new DoctrineDonationEventLogger( $this->entityManager, $this->getDefaultTimeFunction() );

		$this->expectException( DonationEventLogException::class );
		$logger->log( 1234, self::DEFAULT_MESSAGE );
	}

	public function testWhenPersistenceFails_domainExceptionIsThrown(): void {
		$logger = new DoctrineDonationEventLogger(
			ThrowingEntityManager::newInstance( $this ),
			$this->getDefaultTimeFunction()
		);

		$this->expectException( DonationEventLogException::class );
		$logger->log( 1234, self::DEFAULT_MESSAGE );
	}

	public function testWhenNoLogExists_logGetsAdded(): void {
		$donation = new Donation();
		$donation->setId( self::DONATION_ID );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();

		$logger = new DoctrineDonationEventLogger( $this->entityManager, $this->getDefaultTimeFunction() );
		$logger->log( self::DONATION_ID, self::DEFAULT_MESSAGE );
		$expectedLog = [ self::LOG_TIMESTAMP => self::DEFAULT_MESSAGE ];

		$donation = $this->getDonationById( self::DONATION_ID );

		$this->assertNotNull( $donation );
		$data = $donation->getDecodedData();
		$this->assertArrayHasKey( 'log', $data );
		$this->assertEquals( $expectedLog, $data['log'] );
	}

	public function testWhenLogExists_logGetsAppended(): void {
		$donation = new Donation();
		$donation->setId( self::DONATION_ID );
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$donation->encodeAndSetData( [ 'log' => [ '2014-01-01 0:00:00' => 'New year!' ] ] );
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();

		$logger = new DoctrineDonationEventLogger( $this->entityManager, $this->getDefaultTimeFunction() );
		$logger->log( self::DONATION_ID, self::DEFAULT_MESSAGE );
		$expectedLog = [
			'2014-01-01 0:00:00' => 'New year!',
			self::LOG_TIMESTAMP => self::DEFAULT_MESSAGE
		];

		$donation = $this->getDonationById( self::DONATION_ID );

		$this->assertNotNull( $donation );
		$data = $donation->getDecodedData();
		$this->assertArrayHasKey( 'log', $data );
		$this->assertEquals( $expectedLog, $data['log'] );
	}

	private function getDonationById( int $donationId ): ?Donation {
		return $this->entityManager->find( Donation::class, $donationId );
	}

	/**
	 * Return a function that always returns a fixed date
	 *
	 * @return callable
	 */
	private function getDefaultTimeFunction(): callable {
		return static function () {
			return self::LOG_TIMESTAMP;
		};
	}

}
