<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\Entities\Donation;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationEventLogger;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\DonationEventLogException;
use WMDE\Fundraising\Frontend\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\Frontend\DonationContext\Tests\TestEnvironment;

/**
 * @covers WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationEventLogger
 */
class DoctrineDonationEventLoggerTest extends \PHPUnit\Framework\TestCase {

	private const DEFAULT_MESSAGE = 'Log message';
	private const LOG_TIMESTAMP = '2015-10-21 21:00:04';

	/**
	 * @var EntityManager
	 */
	private $entityManager;

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
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();
		$donationId = $donation->getId();

		$logger = new DoctrineDonationEventLogger( $this->entityManager, $this->getDefaultTimeFunction() );

		$logger->log( $donationId, self::DEFAULT_MESSAGE );

		$donation = $this->getDonationById( $donationId );
		$data = $donation->getDecodedData();

		$expectedLog = [
			self::LOG_TIMESTAMP => self::DEFAULT_MESSAGE
		];
		$this->assertArrayHasKey( 'log', $data );
		$this->assertEquals( $expectedLog, $data['log'] );
	}

	public function testWhenLogExists_logGetsAppended(): void {
		$donation = new Donation();
		$donation->encodeAndSetData( [ 'log' => [ '2014-01-01 0:00:00' => 'New year!' ] ] );
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();
		$donationId = $donation->getId();

		$logger = new DoctrineDonationEventLogger( $this->entityManager, $this->getDefaultTimeFunction() );

		$logger->log( $donationId, self::DEFAULT_MESSAGE );

		$donation = $this->getDonationById( $donationId );
		$data = $donation->getDecodedData();

		$expectedLog = [
			'2014-01-01 0:00:00' => 'New year!',
			self::LOG_TIMESTAMP => self::DEFAULT_MESSAGE
		];
		$this->assertArrayHasKey( 'log', $data );
		$this->assertEquals( $expectedLog, $data['log'] );
	}

	private function getDonationById( int $donationId ): Donation {
		return $this->entityManager->find( Donation::class, $donationId );
	}

	// always return fixed date
	private function getDefaultTimeFunction(): callable {
		return function() {
			return self::LOG_TIMESTAMP;
		};
	}

}
