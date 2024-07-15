<?php

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Clock\SystemClock;
use WMDE\Fundraising\DonationContext\DataAccess\DatabaseDonationAnonymizer;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationExistsChecker;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\ModerationReasonRepository;
use WMDE\Fundraising\DonationContext\Domain\AnonymizationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

#[CoversClass( DatabaseDonationAnonymizer::class )]
class DatabaseDonationAnonymizerTest extends TestCase {

	private const DEFAULT_DONATION_ID = 1;

	private DonationRepository $donationRepository;

	private EntityManager $entityManager;

	private \DateTime $anonymizationMarkerTime;
	private \DateInterval $gracePeriod;
	private SystemClock $clock;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->anonymizationMarkerTime = new \DateTime();
		$this->entityManager = $factory->getEntityManager();
		$this->donationRepository = new DoctrineDonationRepository(
			$this->entityManager,
			new DoctrineDonationExistsChecker( $this->entityManager ),
			$this->makeGetPaymentUseCaseStub(),
			new ModerationReasonRepository( $this->entityManager )
		);
		$this->clock = new SystemClock();
		$this->gracePeriod = new \DateInterval( 'P2D' );
	}

	public function testAnonymizeAtReturnsNumberOfAnonymizedDonations(): void {
		$this->entityManager->persist( $this->newExportedDonation( 1 ) );
		$this->entityManager->persist( $this->newExportedDonation( 2 ) );
		$this->entityManager->persist( $this->newExportedDonation( 3 ) );
		$this->entityManager->flush();
		$anonymizer = new DatabaseDonationAnonymizer( $this->donationRepository, $this->entityManager, $this->clock, $this->gracePeriod );

		$count = $anonymizer->anonymizeAt( \DateTimeImmutable::createFromMutable( $this->anonymizationMarkerTime ) );

		$this->assertSame( 3, $count );
		$this->assertNumberOfScrubbedDonations( 3 );
	}

	public function testGivenOneDonation_itIsNotCleanedWhenTimestampDoesNotMatch(): void {
		$this->insertOneRow();
		$yesterday = \DateTimeImmutable::createFromMutable( $this->anonymizationMarkerTime )->modify( '-1 day' );
		$anonymizer = new DatabaseDonationAnonymizer( $this->donationRepository, $this->entityManager, $this->clock, $this->gracePeriod );

		$anonymizer->anonymizeAt( $yesterday );

		$this->assertNumberOfScrubbedDonations( 0 );
	}

	public function testGivenDonation_anonymizeWillAnonymizeIt(): void {
		$this->insertOneRow();
		$anonymizer = new DatabaseDonationAnonymizer( $this->donationRepository, $this->entityManager, $this->clock, $this->gracePeriod );

		$anonymizer->anonymizeWithIds( self::DEFAULT_DONATION_ID );

		$this->assertNumberOfScrubbedDonations( 1 );
	}

	public function testGivenNoDonation_anonymizeWillThrow(): void {
		$missingDonationId = 42;
		$this->expectException( AnonymizationException::class );
		$this->expectExceptionMessageMatches( "/Could not find donation with id $missingDonationId/" );
		$anonymizer = new DatabaseDonationAnonymizer( $this->donationRepository, $this->entityManager, $this->clock, $this->gracePeriod );

		$anonymizer->anonymizeWithIds( $missingDonationId );
	}

	private function assertNumberOfScrubbedDonations( int $expectedNumberOfScrubbedDonations ): void {
		$result = $this->entityManager->getConnection()->executeQuery( 'SELECT COUNT(*) FROM spenden WHERE is_scrubbed = 1' );
		$count = $result->fetchOne();
		$this->assertEquals( $expectedNumberOfScrubbedDonations, $count );
	}

	private function insertOneRow(): void {
		$this->entityManager->persist( $this->newExportedDonation() );
		$this->entityManager->flush();
	}

	private function newExportedDonation( int $id = self::DEFAULT_DONATION_ID ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newExportedirectDebitDoctrineDonation();
		$donation->setDtBackup( $this->anonymizationMarkerTime );
		$donation->setId( $id );
		return $donation;
	}

	private function makeGetPaymentUseCaseStub(): GetPaymentUseCase {
		$stub = $this->createStub( GetPaymentUseCase::class );
		$stub->method( 'getLegacyPaymentDataObject' )->willReturn( $this->createDefaultLegacyData() );
		return $stub;
	}

	private function createDefaultLegacyData(): LegacyPaymentData {
		// Bogus data
		return new LegacyPaymentData(
			999999,
			999,
			'PPL',
			[
				'paymentValue' => 'almostInfinite',
				'paid' => 'certainly'
			],
		);
	}

}
