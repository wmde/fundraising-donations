<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokens;
use WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\EventEmitterSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeEventEmitter;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedDonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingPaymentServiceStub;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\PaymentUrlGenerator\NullGenerator;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\SuccessResponse as PaymentCreationSucceeded;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationUseCase
 * @covers \WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent
 */
class AddDonationUseCaseTest extends TestCase {

	private const UPDATE_TOKEN = 'a very nice token';
	private const ACCESS_TOKEN = 'kindly allow me access';

	public function testWhenValidationSucceeds_successResponseIsCreated(): void {
		$useCase = $this->newValidationSucceedingUseCase();

		$this->assertTrue( $useCase->addDonation( $this->newMinimumDonationRequest() )->isSuccessful() );
	}

	public function testWhenAnonymousDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = $this->newActiveBankTransferCodeGeneratorUseCase();
		$donationRequest = $this->newMinimumDonationRequest();

		$response = $useCase->addDonation( $donationRequest );

		self::assertStringStartsWith( 'XR', $response->getDonation()->getPaymentMethod()->getBankTransferCode() );
	}

	public function testWhenPrivateDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = $this->newActiveBankTransferCodeGeneratorUseCase();
		$donationRequest = $this->newValidAddDonationRequestWithEmail( 'bill.gates@wikimedia.de' );

		$response = $useCase->addDonation( $donationRequest );

		self::assertStringStartsWith( 'XW', $response->getDonation()->getPaymentMethod()->getBankTransferCode() );
	}

	public function testWhenCompanyDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = $this->newActiveBankTransferCodeGeneratorUseCase();
		$donationRequest = $this->newValidCompanyDonationRequest();

		$response = $useCase->addDonation( $donationRequest );

		self::assertStringStartsWith( 'XW', $response->getDonation()->getPaymentMethod()->getBankTransferCode() );
	}

	private function newValidationSucceedingUseCase(): AddDonationUseCase {
		return new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new EventEmitterSpy(),
			new SucceedingPaymentServiceStub()
		);
	}

	private function newActiveBankTransferCodeGeneratorUseCase(): AddDonationUseCase {
		return new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter(),
			new SucceedingPaymentServiceStub(
				new PaymentCreationSucceeded( ValidPayments::ID_BANK_TRANSFER, new NullGenerator() )
			)
		);
	}

	/**
	 * @return DonationNotifier&MockObject
	 */
	private function newMailer(): DonationNotifier {
		return $this->createMock( DonationNotifier::class );
	}

	private function newTokenFetcher(): DonationTokenFetcher {
		return new FixedDonationTokenFetcher(
			new DonationTokens(
				self::ACCESS_TOKEN,
				self::UPDATE_TOKEN
			)
		);
	}

	private function newRepository(): DonationRepository {
		return new FakeDonationRepository();
	}

	public function testValidationFails_responseObjectContainsViolations(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$result = $useCase->addDonation( $this->newMinimumDonationRequest() );
		$this->assertEquals( [ new ConstraintViolation( 'foo', 'bar' ) ], $result->getValidationErrors() );
	}

	public function testValidationFails_responseObjectContainsRequestObject(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$request = $this->newInvalidDonationRequest();
		$useCase->addDonation( $request );
		$this->assertEquals( $this->newInvalidDonationRequest(), $request );
	}

	private function getSucceedingValidatorMock(): AddDonationValidator {
		$validator = $this->getMockBuilder( AddDonationValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$validator->method( 'validate' )->willReturn( new AddDonationValidationResult() );

		return $validator;
	}

	private function getFailingValidatorMock( ConstraintViolation $violation ): AddDonationValidator {
		$validator = $this->getMockBuilder( AddDonationValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$validator->method( 'validate' )->willReturn( new AddDonationValidationResult( $violation ) );

		return $validator;
	}

	private function getSucceedingPolicyValidatorMock(): ModerationService {
		$validator = $this->createStub( ModerationService::class );
		$validator->method( 'moderateDonationRequest' )->willReturn( new ModerationResult() );
		return $validator;
	}

	private function getFailingPolicyValidatorMock(): ModerationService {
		$validator = $this->createStub( ModerationService::class );
		$validator->method( 'needsModeration' )->willReturn( true );
		$moderationResult = new ModerationResult();
		$moderationResult->addModerationReason( new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH ) );
		$validator->method( 'moderateDonationRequest' )->willReturn( $moderationResult );
		return $validator;
	}

	private function getAutoDeletingPolicyValidatorMock(): ModerationService {
		$validator = $this->createStub( ModerationService::class );
		$validator->method( 'moderateDonationRequest' )->willReturn( new ModerationResult() );

		$validator->method( 'isAutoDeleted' )->willReturn( true );

		return $validator;
	}

	private function newMinimumDonationRequest(): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setPaymentCreationRequest( new PaymentCreationRequest(
			100,
			PaymentInterval::OneTime->value,
			'UEB'
		) );
		$donationRequest->setDonorType( DonorType::ANONYMOUS() );
		return $donationRequest;
	}

	private function newInvalidDonationRequest(): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setPaymentCreationRequest( new PaymentCreationRequest(
			100,
			PaymentInterval::OneTime->value,
			'BEZ'
		) );
		$donationRequest->setDonorType( DonorType::ANONYMOUS() );
		return $donationRequest;
	}

	public function testGivenInvalidRequest_noConfirmationEmailIsSent(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$mailer = $this->newMailer();

		$mailer->expects( $this->never() )->method( $this->anything() );

		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			$this->getSucceedingPolicyValidatorMock(),
			$mailer,
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$useCase->addDonation( $this->newMinimumDonationRequest() );
	}

	public function testGivenValidRequest_confirmationEmailIsSent(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$mailer = $this->newMailer();
		$donation = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );

		$mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $this->isInstanceOf( Donation::class ) );

		$useCase = $this->newUseCaseWithMailer( $mailer );

		$useCase->addDonation( $donation );
	}

	public function testGivenValidRequest_moderationEmailIsSent(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$mailer = $this->newMailer();
		$donation = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );

		$mailer->expects( $this->once() )
			->method( 'sendModerationNotificationToAdmin' )
			->with( $this->isInstanceOf( Donation::class ) );

		$useCase = $this->newUseCaseWithMailer( $mailer );

		$useCase->addDonation( $donation );
	}

	public function testGivenValidRequestWithExternalPaymentType_confirmationEmailIsNotSent(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$mailer = $this->newMailer();

		$mailer->expects( $this->never() )->method( 'sendConfirmationFor' );

		$useCase = $this->newUseCaseWithMailer( $mailer );

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setPaymentType( 'PPL' );
		$useCase->addDonation( $request );
	}

	public function testGivenValidRequestWithPolicyViolation_donationIsModerated(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getFailingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$response = $useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );
		$this->assertTrue( $response->getDonation()->isMarkedForModeration() );
	}

	public function testGivenPolicyViolationForExternalPaymentDonation_donationIsNotModerated(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getFailingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setPaymentType( 'PPL' );
		$response = $useCase->addDonation( $request );
		$this->assertFalse( $response->getDonation()->isMarkedForModeration() );
	}

	private function newUseCaseWithMailer( DonationNotifier $mailer ): AddDonationUseCase {
		return new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getSucceedingPolicyValidatorMock(),
			$mailer,
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);
	}

	private function newValidAddDonationRequestWithEmail( string $email ): AddDonationRequest {
		$request = $this->newMinimumDonationRequest();

		$request->setDonorType( DonorType::PERSON() );
		$request->setDonorFirstName( ValidDonation::DONOR_FIRST_NAME );
		$request->setDonorLastName( ValidDonation::DONOR_LAST_NAME );
		$request->setDonorCompany( '' );
		$request->setDonorSalutation( ValidDonation::DONOR_SALUTATION );
		$request->setDonorTitle( ValidDonation::DONOR_TITLE );
		$request->setDonorStreetAddress( ValidDonation::DONOR_STREET_ADDRESS );
		$request->setDonorCity( ValidDonation::DONOR_CITY );
		$request->setDonorPostalCode( ValidDonation::DONOR_POSTAL_CODE );
		$request->setDonorCountryCode( ValidDonation::DONOR_COUNTRY_CODE );
		$request->setDonorEmailAddress( $email );

		return $request;
	}

	private function newValidCompanyDonationRequest(): AddDonationRequest {
		$request = $this->newMinimumDonationRequest();

		$request->setDonorType( DonorType::COMPANY() );
		$request->setDonorFirstName( '' );
		$request->setDonorLastName( '' );
		$request->setDonorCompany( ValidDonation::DONOR_LAST_NAME );
		$request->setDonorSalutation( '' );
		$request->setDonorTitle( '' );
		$request->setDonorStreetAddress( ValidDonation::DONOR_STREET_ADDRESS );
		$request->setDonorCity( ValidDonation::DONOR_CITY );
		$request->setDonorPostalCode( ValidDonation::DONOR_POSTAL_CODE );
		$request->setDonorCountryCode( ValidDonation::DONOR_COUNTRY_CODE );
		$request->setDonorEmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS );

		return $request;
	}

	public function testWhenAdditionWorks_successResponseContainsTokens(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$useCase = $this->newValidationSucceedingUseCase();

		$response = $useCase->addDonation( $this->newMinimumDonationRequest() );

		$this->assertSame( self::UPDATE_TOKEN, $response->getUpdateToken() );
		$this->assertSame( self::ACCESS_TOKEN, $response->getAccessToken() );
	}

	/**
	 * TODO move 'covers' tag for DonationCreatedEvent here when we've improved the PHPCS definitions
	 */
	public function testWhenValidationSucceeds_eventIsEmitted(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$eventEmitter = new EventEmitterSpy();
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			$eventEmitter
		);

		$useCase->addDonation( $this->newValidCompanyDonationRequest() );

		/** @var DonationCreatedEvent[] $events */
		$events = $eventEmitter->getEvents();
		$this->assertCount( 1, $events, 'Only 1 event should be emitted' );
		$this->assertInstanceOf( DonationCreatedEvent::class, $events[0] );
		$this->assertInstanceOf( CompanyName::class, $events[0]->getDonor()->getName() );
	}

	public function testWhenEmailAddressIsBlacklisted_donationIsMarkedAsCancelled(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$repository = $this->newRepository();
		$useCase = new AddDonationUseCase(
			$repository,
			$this->getSucceedingValidatorMock(),
			$this->getAutoDeletingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );
		$this->assertTrue( $repository->getDonationById( 1 )->isCancelled() );
	}

	public function testOptingIntoDonationReceipt_persistedInDonation(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$repository = $this->newRepository();
		$useCase = new AddDonationUseCase(
			$repository,
			$this->getSucceedingValidatorMock(),
			$this->getAutoDeletingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( true );

		$useCase->addDonation( $request );

		$this->assertSame( true, $repository->getDonationById( 1 )->getOptsIntoDonationReceipt() );
	}

	public function testOptingOutOfDonationReceipt_persistedInDonation(): void {
		$this->markTestIncomplete( 'Incomplete due to payment refactoring' );
		$repository = $this->newRepository();
		$useCase = new AddDonationUseCase(
			$repository,
			$this->getSucceedingValidatorMock(),
			$this->getAutoDeletingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTokenFetcher(),
			new FakeEventEmitter()
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( false );

		$useCase->addDonation( $request );

		$this->assertSame( false, $repository->getDonationById( 1 )->getOptsIntoDonationReceipt() );
	}

}
