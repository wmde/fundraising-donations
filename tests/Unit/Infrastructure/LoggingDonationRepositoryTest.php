<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Unit\Infrastructure;

use Psr\Log\LogLevel;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\Frontend\DonationContext\Infrastructure\LoggingDonationRepository;
use WMDE\Fundraising\Frontend\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\Frontend\DonationContext\Tests\Data\ValidDonation;
use WMDE\PsrLogTestDoubles\LoggerSpy;

/**
 * @covers WMDE\Fundraising\Frontend\DonationContext\Infrastructure\LoggingDonationRepository
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class LoggingDonationRepositoryTest extends \PHPUnit\Framework\TestCase {

	public function testWhenGetDonationByIdThrowException_itIsLogged(): void {
		$loggingRepo = new LoggingDonationRepository(
			$this->newThrowingRepository(),
			new LoggerSpy()
		);

		$this->expectException( GetDonationException::class );
		$loggingRepo->getDonationById( 1337 );
	}

	private function newThrowingRepository(): DonationRepository {
		$repository = $this->createMock( DonationRepository::class );

		$repository->expects( $this->any() )
			->method( 'getDonationById' )
			->willThrowException( new GetDonationException() );

		$repository->expects( $this->any() )
			->method( 'storeDonation' )
			->willThrowException( new StoreDonationException() );

		return $repository;
	}

	public function testWhenGetDonationByIdThrowException_itIsNotFullyCaught(): void {
		$logger = new LoggerSpy();

		$loggingRepo = new LoggingDonationRepository(
			$this->newThrowingRepository(),
			$logger
		);

		try {
			$loggingRepo->getDonationById( 1337 );
		}
		catch ( GetDonationException $ex ) {
		}

		$this->assertExceptionLoggedAsCritical( GetDonationException::class, $logger );
	}

	private function assertExceptionLoggedAsCritical( string $expectedExceptionType, LoggerSpy $logger ): void {
		$this->assertCount( 1, $logger->getLogCalls(), 'There should be exactly one log call' );

		$logCall = $logger->getLogCalls()->getFirstCall();

		$this->assertSame( LogLevel::CRITICAL, $logCall->getLevel() );
		$this->assertArrayHasKey( 'exception', $logCall->getContext(), 'the log context should contain an exception element' );
		$this->assertInstanceOf( $expectedExceptionType, $logCall->getContext()['exception'] );
	}

	public function testWhenGetDonationByIdDoesNotThrow_returnValueIsReturnedWithoutLogging(): void {
		$logger = new LoggerSpy();
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( 1337 );

		$loggingRepo = new LoggingDonationRepository(
			new FakeDonationRepository( $donation ),
			$logger
		);

		$this->assertEquals( $donation, $loggingRepo->getDonationById( 1337 ) );
		$logger->assertNoLoggingCallsWhereMade();
	}

	public function testWhenStoreDonationThrowException_itIsLogged(): void {
		$loggingRepo = new LoggingDonationRepository(
			$this->newThrowingRepository(),
			new LoggerSpy()
		);

		$this->expectException( StoreDonationException::class );
		$loggingRepo->storeDonation( ValidDonation::newDirectDebitDonation() );
	}

	public function testWhenStoreDonationThrowException_itIsNotFullyCaught(): void {
		$logger = new LoggerSpy();

		$loggingRepo = new LoggingDonationRepository(
			$this->newThrowingRepository(),
			$logger
		);

		try {
			$loggingRepo->storeDonation( ValidDonation::newDirectDebitDonation() );
		}
		catch ( StoreDonationException $ex ) {
		}

		$this->assertExceptionLoggedAsCritical( StoreDonationException::class, $logger );
	}

}
