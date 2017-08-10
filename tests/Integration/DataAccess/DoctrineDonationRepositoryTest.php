<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\Entities\Donation as DoctrineDonation;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\Frontend\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\Frontend\DonationContext\Tests\Data\IncompleteDoctrineDonation;
use WMDE\Fundraising\Frontend\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\Frontend\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\Frontend\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\Frontend\DonationContext\Tests\TestEnvironment;

/**
 * @covers \WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationRepository
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DoctrineDonationRepositoryTest extends TestCase {

	private const ID_OF_DONATION_NOT_IN_DB = 35505;

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$factory->disableDoctrineSubscribers();
		$this->entityManager = $factory->getEntityManager();
		parent::setUp();
	}

	public function testValidDonationGetPersisted(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$this->newRepository()->storeDonation( $donation );

		$expectedDoctrineEntity = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$expectedDoctrineEntity->setId( $donation->getId() );

		$this->assertDoctrineEntityIsInDatabase( $expectedDoctrineEntity );
	}

	private function newRepository(): DoctrineDonationRepository {
		return new DoctrineDonationRepository( $this->entityManager );
	}

	private function assertDoctrineEntityIsInDatabase( DoctrineDonation $expected ): void {
		$actual = $this->getDoctrineDonationById( $expected->getId() );

		$this->assertNotNull( $actual->getCreationTime() );
		$actual->setCreationTime( null );

		$this->assertEquals( $expected->getDecodedData(), $actual->getDecodedData() );

		$this->assertEquals( $expected, $actual );
	}

	private function getDoctrineDonationById( int $id ): DoctrineDonation {
		$donationRepo = $this->entityManager->getRepository( DoctrineDonation::class );
		$donation = $donationRepo->find( $id );
		$this->assertInstanceOf( DoctrineDonation::class, $donation );
		return $donation;
	}

	public function testWhenPersistenceFails_domainExceptionIsThrown(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$repository = new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) );

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testNewDonationPersistenceRoundTrip(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$repository = $this->newRepository();

		$repository->storeDonation( $donation );

		$this->assertEquals(
			$donation,
			$repository->getDonationById( $donation->getId() )
		);
	}

	public function testNewBankTransferPayment_persistingSavesBankTransferCode(): void {
		$donation = ValidDonation::newBankTransferDonation();

		$repository = $this->newRepository();

		$repository->storeDonation( $donation );

		$retrievedDonation = $repository->getDonationById( $donation->getId() );
		/**
		 * @var $payment BankTransferPayment
		 */
		$payment = $retrievedDonation->getPaymentMethod();
		$this->assertSame( ValidDonation::PAYMENT_BANK_TRANSFER_CODE, $payment->getBankTransferCode() );
	}

	public function testNewSofortPayment_persistingSavesBankTransferCode(): void {
		$donation = ValidDonation::newSofortDonation();

		$repository = $this->newRepository();

		$repository->storeDonation( $donation );

		$retrievedDonation = $repository->getDonationById( $donation->getId() );
		/**
		 * @var $payment SofortPayment
		 */
		$payment = $retrievedDonation->getPaymentMethod();
		$this->assertSame( ValidDonation::PAYMENT_BANK_TRANSFER_CODE, $payment->getBankTransferCode() );
	}

	public function testSofortPaymentDateUpdate_paymentEntityIdStaysTheSame(): void {
		$donation = ValidDonation::newSofortDonation();
		$repository = $this->newRepository();
		$repository->storeDonation( $donation );

		$paymentId = $this->getDoctrineDonationById( $donation->getId() )->getPayment()->getId();

		/**
		 * @var $sofortPayment SofortPayment
		 */
		$sofortPayment = $donation->getPayment()->getPaymentMethod();
		$sofortPayment->setConfirmedAt( new \DateTime( '2017-08-03T12:44:42' ) );

		$repository->storeDonation( $donation );

		$this->assertSame( $paymentId, $this->getDoctrineDonationById( $donation->getId() )->getPayment()->getId() );
	}

	public function testWhenDonationAlreadyExists_persistingCausesUpdate(): void {
		$repository = $this->newRepository();

		$donation = ValidDonation::newDirectDebitDonation();
		$repository->storeDonation( $donation );

		// It is important a new instance is created here to test "detached entity" handling
		$newDonation = ValidDonation::newDirectDebitDonation();
		$newDonation->assignId( $donation->getId() );
		$newDonation->cancel();
		$repository->storeDonation( $newDonation );

		$this->assertEquals( $newDonation, $repository->getDonationById( $newDonation->getId() ) );
	}

	public function testWhenDonationDoesNotExist_getDonationReturnsNull(): void {
		$repository = $this->newRepository();

		$this->assertNull( $repository->getDonationById( self::ID_OF_DONATION_NOT_IN_DB ) );
	}

	public function testWhenDoctrineThrowsException_domainExceptionIsThrown(): void {
		$repository = new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) );

		$this->expectException( GetDonationException::class );
		$repository->getDonationById( self::ID_OF_DONATION_NOT_IN_DB );
	}

	public function testWhenDonationDoesNotExist_persistingCausesException(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( self::ID_OF_DONATION_NOT_IN_DB );

		$repository = $this->newRepository();

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testWhenDeletionDateGetsSet_repositoryNoLongerReturnsEntity(): void {
		$donation = $this->createDeletedDonation();
		$repository = $this->newRepository();

		$this->assertNull( $repository->getDonationById( $donation->getId() ) );
	}

	private function createDeletedDonation(): Donation {
		$donation = ValidDonation::newDirectDebitDonation();
		$repository = $this->newRepository();
		$repository->storeDonation( $donation );
		$doctrineDonation = $repository->getDoctrineDonationById( $donation->getId() );
		$doctrineDonation->setDeletionTime( new \DateTime() );
		$this->entityManager->flush();
		return $donation;
	}

	public function testWhenDeletionDateGetsSet_repositoryNoLongerPersistsEntity(): void {
		$donation = $this->createDeletedDonation();
		$repository = $this->newRepository();

		$this->expectException( StoreDonationException::class );
		$repository->storeDonation( $donation );
	}

	public function testDataFieldsAreRetainedOrUpdatedOnUpdate(): void {
		$doctrineDonation = $this->getNewlyCreatedDoctrineDonation();

		$doctrineDonation->encodeAndSetData( array_merge(
			$doctrineDonation->getDecodedData(),
			[
				'untouched' => 'value',
				'vorname' => 'potato',
				'another' => 'untouched',
			]
		) );

		$this->entityManager->flush();

		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( $doctrineDonation->getId() );

		$this->newRepository()->storeDonation( $donation );

		$data = $this->getDoctrineDonationById( $donation->getId() )->getDecodedData();

		$this->assertSame( 'value', $data['untouched'] );
		$this->assertNotSame( 'potato', $data['vorname'] );
		$this->assertSame( 'untouched', $data['another'] );
	}

	/**
	 * The backend application data purge script sets the personal information to empty strings
	 */
	public function testGivenPurgedDonationNoDonorIsCreated(): void {
		$doctrineDonation = $this->getNewlyCreatedDoctrineDonation();
		$doctrineDonation->setDtBackup( new \DateTime() );
		$this->entityManager->persist( $doctrineDonation );
		$this->entityManager->flush();

		$donation = $this->newRepository()->getDonationById( $doctrineDonation->getId() );

		$this->assertNull( $donation->getDonor() );
	}

	public function testGivenDonationUpdateWithoutDonorInformation_DonorNameStaysTheSame(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$this->newRepository()->storeDonation( $donation );

		$anonymousDonation = ValidDonation::newBookedAnonymousPayPalDonationUpdate( $donation->getId() );
		$this->newRepository()->storeDonation( $anonymousDonation );

		$doctrineDonation = $this->getDoctrineDonationById( $donation->getId() );

		$this->assertSame( $donation->getDonor()->getName()->getFullName(), $doctrineDonation->getDonorFullName() );
	}

	private function getNewlyCreatedDoctrineDonation(): DoctrineDonation {
		$donation = ValidDonation::newDirectDebitDonation();
		$this->newRepository()->storeDonation( $donation );
		return $this->getDoctrineDonationById( $donation->getId() );
	}

	public function testCommentGetPersistedAndRetrieved(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$repository = $this->newRepository();
		$repository->storeDonation( $donation );

		$retrievedDonation = $repository->getDonationById( $donation->getId() );

		$this->assertEquals( $donation, $retrievedDonation );
	}

	public function testPersistingDonationWithoutCommentCausesCommentToBeCleared(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$repository = $this->newRepository();
		$repository->storeDonation( $donation );

		$newDonation = ValidDonation::newDirectDebitDonation();
		$newDonation->assignId( $donation->getId() );

		$repository->storeDonation( $newDonation );

		$expectedDoctrineEntity = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$expectedDoctrineEntity->setId( $donation->getId() );
		$expectedDoctrineEntity->setComment( '' );
		$expectedDoctrineEntity->setIsPublic( false );
		$expectedDoctrineEntity->setPublicRecord( '' );

		$this->assertDoctrineEntityIsInDatabase( $expectedDoctrineEntity );
	}

	public function testDonationWithIncompletePaypalDataCanBeLoaded(): void {
		$donationId = $this->createPaypalDonationWithMissingFields();
		$repository = $this->newRepository();
		$donation = $repository->getDonationById( $donationId );
		/** @var PayPalPayment $paypalPayment */
		$paypalPayment = $donation->getPaymentMethod();
		$this->assertNotNull( $paypalPayment->getPayPalData() );
		$this->assertSame( '', $paypalPayment->getPayPalData()->getFirstName() );
	}

	private function createPaypalDonationWithMissingFields(): int {
		$doctrineDonation = IncompleteDoctrineDonation::newPaypalDonationWithMissingFields();
		$this->entityManager->persist( $doctrineDonation );
		$this->entityManager->flush();
		return $doctrineDonation->getId();
	}

	public function testDonationWithMissingTrackingInformationDataCanBeLoaded(): void {
		$donationId = $this->createPaypalDonationWithMissingTracking();
		$repository = $this->newRepository();
		$donation = $repository->getDonationById( $donationId );
		$info = $donation->getTrackingInfo();
		$this->assertNotNull( $info );
		$this->assertSame( '', $info->getColor() );
		$this->assertSame( 0, $info->getTotalImpressionCount() );
	}

	private function createPaypalDonationWithMissingTracking(): int {
		$doctrineDonation = IncompleteDoctrineDonation::newPaypalDonationWithMissingTrackingData();
		$this->entityManager->persist( $doctrineDonation );
		$this->entityManager->flush();
		return $doctrineDonation->getId();
	}

	public function testDonationWithIncompleteBankDataCanBeLoaded(): void {
		$donationId = $this->createDonationWithIncompleteBankData();
		$repository = $this->newRepository();
		$donation = $repository->getDonationById( $donationId );
		/** @var DirectDebitPayment $paymentMethod */
		$paymentMethod = $donation->getPaymentMethod();
		$this->assertNotNull( $paymentMethod->getBankData() );
		$this->assertSame( '', $paymentMethod->getBankData()->getIban()->toString() );
	}

	private function createDonationWithIncompleteBankData(): ?int {
		$doctrineDonation = IncompleteDoctrineDonation::newDirectDebitDonationWithMissingFields();
		$this->entityManager->persist( $doctrineDonation );
		$this->entityManager->flush();
		return $doctrineDonation->getId();
	}

	public function testDonationWithIncompleteCreditcardDataCanBeLoaded(): void {
		$donationId = $this->createDonationWithIncompleteCreditcardData();
		$repository = $this->newRepository();
		$donation = $repository->getDonationById( $donationId );
		/** @var CreditCardPayment $paymentMethod */
		$paymentMethod = $donation->getPaymentMethod();
		$this->assertNotNull( $paymentMethod->getCreditCardData() );
		$this->assertSame( '', $paymentMethod->getCreditCardData()->getTitle() );
	}

	private function createDonationWithIncompleteCreditcardData(): ?int {
		$doctrineDonation = IncompleteDoctrineDonation::newCreditcardDonationWithMissingFields();
		$this->entityManager->persist( $doctrineDonation );
		$this->entityManager->flush();
		return $doctrineDonation->getId();
	}

}
