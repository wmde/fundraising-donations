<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\UpdateDonor;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationExistsChecker;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\ModerationReasonRepository;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\NoName;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\NotificationLogStub;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\ModerateDonation\ModerateDonationUseCase;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

#[CoversClass( ModerateDonationUseCase::class )]
class ApproveAsAnonymousUseCaseTest extends TestCase {

	private const DONATION_ID = 1;
	private const AMOUNT_IN_CENTS = 999999;
	private const AMOUNT = self::AMOUNT_IN_CENTS / 100;
	private const INTERVAL = 999;
	private const PAYMENT_TYPE = 'PPL';
	private const CREATED_DATE = '2026-03-08 10:04:27';

	private EntityManager $entityManager;
	private DoctrineDonationRepository $repository;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->entityManager = $factory->getEntityManager();

		$this->repository = new DoctrineDonationRepository(
			$this->entityManager,
			new DoctrineDonationExistsChecker( $this->entityManager ),
			$this->makeGetPaymentUseCaseStub(),
			new ModerationReasonRepository( $this->entityManager )
		);
	}

	public function testApproveAsAnonymous_persistsChangesInDatabase(): void {
		$this->entityManager->persist( $this->newModeratedDonation() );
		$this->entityManager->flush();

		$useCase = $this->newApproveAsAnonymousUseCase();
		$useCase->approveAsAnonymous( self::DONATION_ID, 'adminUser' );

		$scrubbedDonation = $this->entityManager->getConnection()
			->executeQuery( 'SELECT * FROM spenden WHERE id = ' . self::DONATION_ID )
			->fetchAssociative();

		$this->assertIsArray( $scrubbedDonation );
		$this->assertSame( 'B', $scrubbedDonation[ 'status' ] );
		$this->assertSame( NoName::DISPLAY_NAME, $scrubbedDonation[ 'name' ] );
		$this->assertSame( '', $scrubbedDonation[ 'ort' ] );
		$this->assertSame( 0, $scrubbedDonation[ 'info' ] );
		$this->assertSame( 0, $scrubbedDonation[ 'bescheinigung' ] );
		$this->assertSame( '', $scrubbedDonation[ 'eintrag' ] );
		$this->assertEquals( self::AMOUNT, $scrubbedDonation[ 'betrag' ] );
		$this->assertSame( self::INTERVAL, $scrubbedDonation[ 'periode' ] );
		$this->assertSame( self::PAYMENT_TYPE, $scrubbedDonation[ 'zahlweise' ] );
		$this->assertSame( '', $scrubbedDonation[ 'kommentar' ] );
		$this->assertSame( '', $scrubbedDonation[ 'ueb_code' ] );
		$this->assertNull( $scrubbedDonation[ 'source' ] );
		$this->assertSame( '', $scrubbedDonation[ 'remote_addr' ] );
		$this->assertNull( $scrubbedDonation[ 'hash' ] );
		$this->assertSame( 0, $scrubbedDonation[ 'is_public' ] );
		$this->assertSame( 0, $scrubbedDonation[ 'is_scrubbed' ] );
		$this->assertSame( self::CREATED_DATE, $scrubbedDonation[ 'dt_new' ] );
		$this->assertNull( $scrubbedDonation[ 'dt_del' ] );
		$this->assertNull( $scrubbedDonation[ 'dt_exp' ] );
		$this->assertNull( $scrubbedDonation[ 'dt_gruen' ] );
		$this->assertNull( $scrubbedDonation[ 'dt_backup' ] );
		$this->assertSame( 1, $scrubbedDonation[ 'payment_id' ] );
		$this->assertSame( 3, $scrubbedDonation[ 'impression_count' ] );
		$this->assertSame( 1, $scrubbedDonation[ 'banner_impression_count' ] );
		$this->assertNull( $scrubbedDonation[ 'tracking_id' ] );

		// @phpstan-ignore argument.type
		$dataBlob = unserialize( base64_decode( $scrubbedDonation[ 'data' ] ) );
		$this->assertIsArray( $dataBlob );
		$this->assertSame( 3, $dataBlob[ 'impCount' ] );
		$this->assertSame( 1, $dataBlob[ 'bImpCount' ] );
		$this->assertSame( 'test/gelb', $dataBlob[ 'tracking' ] );
		$this->assertSame( 'nyan', $dataBlob[ 'anrede' ] );
		$this->assertArrayNotHasKey( 'titel', $dataBlob );
		$this->assertArrayNotHasKey( 'vorname', $dataBlob );
		$this->assertArrayNotHasKey( 'nachname', $dataBlob );
		$this->assertArrayNotHasKey( 'strasse', $dataBlob );
		$this->assertArrayNotHasKey( 'plz', $dataBlob );
		$this->assertArrayNotHasKey( 'ort', $dataBlob );
		$this->assertArrayNotHasKey( 'email', $dataBlob );
	}

	private function newApproveAsAnonymousUseCase(): ModerateDonationUseCase {
		return new ModerateDonationUseCase(
			$this->repository,
			new DonationEventLoggerSpy(),
			$this->createStub( DonationNotifier::class ),
			new NotificationLogStub()
		);
	}

	private function makeGetPaymentUseCaseStub(): GetPaymentUseCase {
		return $this->createConfiguredStub(
			GetPaymentUseCase::class,
			[ 'getLegacyPaymentDataObject' => $this->createDefaultLegacyData() ]
		);
	}

	private function newModeratedDonation(): DoctrineDonation {
		$donation = ValidDoctrineDonation::newCreditCardDonation();
		$donation->setId( self::DONATION_ID );
		$donation->setCreationTime( new \DateTime( self::CREATED_DATE ) );
		$donation->setModerationReasons( new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION ) );
		$donation->setStatus( 'P' );
		$donation->setComment( 'nasty commentses' );
		return $donation;
	}

	private function createDefaultLegacyData(): LegacyPaymentData {
		return new LegacyPaymentData(
			self::AMOUNT_IN_CENTS,
			self::INTERVAL,
			self::PAYMENT_TYPE,
			[
				'ext_payment_id' => 'PAY-1B56960729604235TKQQIYVY',
				'paypal_payer_id' => 'payer_id',
				'paypal_subscr_id' => 'subscr_id',
				'paypal_payer_status' => 'payer_status',
				'paypal_mc_gross' => 'mc_gross',
				'paypal_mc_currency' => 'mc_currency',
				'paypal_mc_fee' => 'mc_fee',
				'paypal_settle_amount' => 'settle_amount',
				'ext_subscr_id' => 'subscr_id',
				'ext_payment_type' => 'payment_type',
				'ext_payment_status' => 'payment_status',
				'ext_payment_account' => 'payer_id',
				'ext_payment_timestamp' => 'payment_date',
			],
		);
	}
}
