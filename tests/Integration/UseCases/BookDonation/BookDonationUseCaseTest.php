<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\BookDonation;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationExistsChecker;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\DataAccess\ModerationReasonRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Services\PaymentBookingService;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayPalNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\StaticDonationIdRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;
use WMDE\Fundraising\DonationContext\UseCases\BookDonationUseCase\BookDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\Model\LegacyPaymentData;
use WMDE\Fundraising\PaymentContext\UseCases\BookPayment\SuccessResponse;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

#[CoversClass( BookDonationUseCase::class )]
class BookDonationUseCaseTest extends TestCase {

	private EntityManager $entityManager;
	private DoctrineDonationRepository $repository;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->entityManager = $factory->getEntityManager();

		$this->repository = new DoctrineDonationRepository(
			$this->entityManager,
			new DoctrineDonationExistsChecker( $this->entityManager ),
			$this->createConfiguredStub( GetPaymentUseCase::class, [ 'getLegacyPaymentDataObject' => $this->createDefaultLegacyData() ] ),
			new ModerationReasonRepository( $this->entityManager )
		);
	}

	public function newBookDonationUseCase(
		?DonationIdRepository $idGenerator = null,
		?DonationRepository $repository = null,
		?DonationAuthorizationChecker $authorizer = null,
		?DonationNotifier $notifier = null,
		?PaymentBookingService $paymentBookingService = null,
		?DonationEventLogger $eventLogger = null,
	): BookDonationUseCase {
		return new BookDonationUseCase(
			$idGenerator ?? new StaticDonationIdRepository(),
			$repository ?? new FakeDonationRepository( ValidDonation::newIncompletePayPalDonation() ),
			authorizationService: $authorizer ?? new SucceedingDonationAuthorizer(),
			notifier: $notifier ?? $this->createStub( DonationNotifier::class ),
			paymentBookingService: $paymentBookingService ?? $this->createConfiguredStub( PaymentBookingService::class, [ 'bookPayment' => new SuccessResponse() ] ),
			eventLogger: $eventLogger ?? $this->createStub( DonationEventLogger::class )
		);
	}

	/**
	 * When we added functionality to convert a donation to anonymous we noticed that the converters
	 * were explicitly set up to preserve donor data. Our suspicion was that the reason for this was
	 * to ensure booking a donation from an IPN doesn't remove donor info. This test is to make
	 * absolutely sure that the data in the database is preserved when the donation is booked.
	 *
	 * @return void
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Doctrine\ORM\Exception\ORMException
	 * @throws \Doctrine\ORM\OptimisticLockException
	 */
	public function testBookingDonationPreservesDonationData(): void {
		$donation = ValidDoctrineDonation::newIncompletePayPalDonation();
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();

		$useCase = $this->newBookDonationUseCase( repository: $this->repository );

		// @phpstan-ignore argument.type
		$request = ValidPayPalNotificationRequest::newInstantPayment( $donation->getId() );
		$useCase->handleNotification( $request );

		$bookedDonation = $this->entityManager->getConnection()
			->executeQuery( 'SELECT * FROM spenden WHERE id = ' . $donation->getId() )
			->fetchAssociative();

		$this->assertIsArray( $bookedDonation );
		$this->assertEquals( Donation::STATUS_EXTERNAL_BOOKED, $bookedDonation[ 'status' ] );
		$this->assertEquals( $donation->getDonorFullName(), $bookedDonation[ 'name' ] );
		$this->assertEquals( $donation->getDonorCity(), $bookedDonation[ 'ort' ] );
		$this->assertEquals( $donation->getDonorEmail(), $bookedDonation[ 'email' ] );
		$this->assertEquals( $donation->getDonorOptsIntoNewsletter(), $bookedDonation[ 'info' ] );
		$this->assertEquals( $donation->getDonationReceipt(), $bookedDonation[ 'bescheinigung' ] );
		$this->assertEquals( $donation->getAmount(), $bookedDonation[ 'betrag' ] );
		$this->assertEquals( $donation->getPaymentIntervalInMonths(), $bookedDonation[ 'periode' ] );
		$this->assertEquals( $donation->getPaymentType(), $bookedDonation[ 'zahlweise' ] );
		$this->assertEquals( $donation->getImpressionCount(), $bookedDonation[ 'impression_count' ] );
		$this->assertEquals( $donation->getBannerImpressionCount(), $bookedDonation[ 'banner_impression_count' ] );

		// @phpstan-ignore argument.type
		$dataBlob = unserialize( base64_decode( $bookedDonation[ 'data' ] ) );
		$this->assertIsArray( $dataBlob );
		$this->assertEquals( $donation->getDecodedData()[ 'impCount' ], $dataBlob[ 'impCount' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'bImpCount' ], $dataBlob[ 'bImpCount' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'tracking' ], $dataBlob[ 'tracking' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'adresstyp' ], $dataBlob[ 'adresstyp' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'anrede' ], $dataBlob[ 'anrede' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'titel' ], $dataBlob[ 'titel' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'vorname' ], $dataBlob[ 'vorname' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'nachname' ], $dataBlob[ 'nachname' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'strasse' ], $dataBlob[ 'strasse' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'plz' ], $dataBlob[ 'plz' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'ort' ], $dataBlob[ 'ort' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'country' ], $dataBlob[ 'country' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'email' ], $dataBlob[ 'email' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'ext_payment_id' ], $dataBlob[ 'ext_payment_id' ] );
		$this->assertEquals( $donation->getDecodedData()[ 'paypal_payer_id' ], $dataBlob[ 'paypal_payer_id' ] );
	}

	private function createDefaultLegacyData(): LegacyPaymentData {
		return new LegacyPaymentData(
			99999,
			42,
			'PPL',
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
