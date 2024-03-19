<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\LoggingDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\PsrLogTestDoubles\LoggerSpy;

#[CoversClass( LoggingDonationRepository::class )]
class LoggingDonationRepositoryTest extends TestCase {

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
		} catch ( GetDonationException $ex ) {
		}

		$this->assertExceptionLoggedAsCritical( GetDonationException::class, $logger );
	}

	/**
	 * @param class-string<object> $expectedExceptionType
	 * @param LoggerSpy $logger
	 *
	 * @return void
	 */
	private function assertExceptionLoggedAsCritical( string $expectedExceptionType, LoggerSpy $logger ): void {
		$this->assertCount( 1, $logger->getLogCalls(), 'There should be exactly one log call' );

		$logCall = $logger->getLogCalls()->getFirstCall();

		$this->assertNotNull( $logCall );
		$this->assertSame( LogLevel::CRITICAL, $logCall->getLevel() );
		$this->assertArrayHasKey(
			'exception',
			$logCall->getContext(),
			'the log context should contain an exception element'
		);
		$this->assertInstanceOf( $expectedExceptionType, $logCall->getContext()['exception'] );
	}

	public function testWhenGetDonationByIdDoesNotThrow_returnValueIsReturnedWithoutLogging(): void {
		$logger = new LoggerSpy();
		$donation = ValidDonation::newDirectDebitDonation( 1337 );

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
		} catch ( StoreDonationException $ex ) {
		}

		$this->assertExceptionLoggedAsCritical( StoreDonationException::class, $logger );
	}

}
