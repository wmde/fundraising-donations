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

	public function testAnonymizeAllReturnsNumberOfAnonymizedDonations(): void {
		$this->markTestSkipped( 'This tests that donations that are moderated for high amounts and older than 2 days do not get exported, but others that' .
			' donations moderated for other reasons do. We currently do not anonymise donations over 2 days so this test will fail' );

		// @phpstan-ignore-next-line
		$this->insertExampleDonations();
		$anonymizer = new DatabaseDonationAnonymizer( $this->donationRepository, $this->entityManager, $this->clock, $this->gracePeriod );

		$count = $anonymizer->anonymizeAll();

		$this->assertSame( 5, $count );
		$this->assertNumberOfScrubbedDonations( 6 );
	}

	public function testAnonymizeAllReturnsNumberOfAnonymizedDonations_andDoesntAnonymizeDonationsMoreThanTwoDaysOld(): void {
		$this->insertExampleDonations();
		$anonymizer = new DatabaseDonationAnonymizer( $this->donationRepository, $this->entityManager, $this->clock, $this->gracePeriod );

		$count = $anonymizer->anonymizeAll();

		$this->assertSame( 3, $count );
		$this->assertNumberOfScrubbedDonations( 4 );
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

	public function testDoesNotAnonymiseModeratedForAmountTooHighDonations(): void {
		$threeDaysAgo = \DateTime::createFromImmutable( $this->clock->now()->modify( '-3 days' ) );
		$this->entityManager->persist( $this->newModeratedAmountTooHighDonation( 1, $threeDaysAgo ) );
		$this->entityManager->flush();

		$anonymizer = new DatabaseDonationAnonymizer( $this->donationRepository, $this->entityManager, $this->clock, $this->gracePeriod );

		$count = $anonymizer->anonymizeAll();

		$this->assertSame( 0, $count );
		$this->assertNumberOfScrubbedDonations( 0 );
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

	private function newScrubbedDonation( int $id = self::DEFAULT_DONATION_ID ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newScrubbedDonation();
		$donation->setId( $id );
		return $donation;
	}

	private function newExportedDonation( int $id = self::DEFAULT_DONATION_ID ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newExportedirectDebitDoctrineDonation();
		$donation->setDtBackup( $this->anonymizationMarkerTime );
		$donation->setId( $id );
		return $donation;
	}

	private function newModeratedAmountTooHighDonation( int $id, \DateTime $donationDate ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newModeratedForAmountTooHighDonation();
		$donation->setCreationTime( $donationDate );
		$donation->setId( $id );
		return $donation;
	}

	private function newModeratedContentViolationDonation( int $id, \DateTime $donationDate ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newModeratedForContentViolationDonation();
		$donation->setCreationTime( $donationDate );
		$donation->setId( $id );
		return $donation;
	}

	private function newUnExportedDonation( int $id, \DateTime $donationDate ): DoctrineDonation {
		$donation = ValidDoctrineDonation::newIncompletePaypalDonation();
		$donation->setCreationTime( $donationDate );
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

	private function insertExampleDonations(): void {
		// Insert 3 exported donations, they should be scrubbed
		$this->entityManager->persist( $this->newExportedDonation( 1 ) );
		$this->entityManager->persist( $this->newExportedDonation( 2 ) );
		$this->entityManager->persist( $this->newExportedDonation( 3 ) );

		// Insert un-exported donation that is older than two days, that should also be scrubbed
		$threeDaysAgo = \DateTime::createFromImmutable( $this->clock->now()->modify( '-3 days' ) );
		$this->entityManager->persist( $this->newUnExportedDonation( 4, $threeDaysAgo ) );

		// Insert un-exported donation that is just created, that should NOT be scrubbed
		$this->entityManager->persist( $this->newUnExportedDonation( 5, \DateTime::createFromImmutable( $this->clock->now() ) ) );

		// Insert an already scrubbed donation, that should NOT be scrubbed
		$this->entityManager->persist( $this->newScrubbedDonation( 6 ) );

		// A donation that has been moderated for language violation
		$this->entityManager->persist( $this->newModeratedContentViolationDonation( 7, \DateTime::createFromImmutable( $this->clock->now() ) ) );

		// A donation that has been moderated for language violation a few days ago
		$this->entityManager->persist( $this->newModeratedContentViolationDonation( 8, $threeDaysAgo ) );

		// A donation that has been moderated for a high amount a few days ago
		$this->entityManager->persist( $this->newModeratedAmountTooHighDonation( 9, $threeDaysAgo ) );

		$this->entityManager->flush();
	}
}
