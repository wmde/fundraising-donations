<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokens;
use WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\CreatePaymentServiceSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\EventEmitterSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedDonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\StaticDonationIdRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingPaymentServiceStub;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\UrlGeneratorSpy;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\CreatePaymentService;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\PaymentUrlGenerator\NullGenerator;
use WMDE\Fundraising\PaymentContext\Domain\PaymentUrlGenerator\PaymentProviderURLGenerator;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse as PaymentCreationFailed;
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
	private const PAYMENT_PROVIDER_URL = 'https://paypal.example.com/';

	public function testWhenValidationSucceeds_successResponseIsCreated(): void {
		$useCase = $this->makeUseCase();

		$response = $useCase->addDonation( $this->newMinimumDonationRequest() );

		$this->assertTrue( $response->isSuccessful() );
	}

	public function testWhenAnonymousDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$paymentService = new CreatePaymentServiceSpy();
		$useCase = $this->makeUseCase( paymentService: $paymentService );

		$useCase->addDonation( $this->newMinimumDonationRequest() );

		$lastRequest = $paymentService->getLastRequest();
		$this->assertSame( 'XR', $lastRequest->transferCodePrefix );
	}

	public function testWhenPrivateDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$paymentService = new CreatePaymentServiceSpy();
		$useCase = $this->makeUseCase( paymentService: $paymentService );
		$donationRequest = $this->newValidAddDonationRequestWithEmail( 'bill.gates@wikimedia.de' );

		$useCase->addDonation( $donationRequest );

		$lastRequest = $paymentService->getLastRequest();
		$this->assertSame( 'XW', $lastRequest->transferCodePrefix );
	}

	public function testWhenCompanyDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$paymentService = new CreatePaymentServiceSpy();
		$useCase = $this->makeUseCase( paymentService: $paymentService );
		$donationRequest = $this->newValidCompanyDonationRequest();

		$useCase->addDonation( $donationRequest );

		$lastRequest = $paymentService->getLastRequest();
		$this->assertSame( 'XW', $lastRequest->transferCodePrefix );
	}

	public function testWhenValidationFails_responseObjectContainsViolations(): void {
		$constraintViolation = new ConstraintViolation( 'XX', 'name_not_valid' );
		$useCase = $this->makeUseCase( donationValidator: $this->makeFakeFailingDonationValidator( $constraintViolation ) );

		$result = $useCase->addDonation( $this->newInvalidDonationRequest() );

		$this->assertEquals( [ $constraintViolation ], $result->getValidationErrors() );
	}

	public function testWhenPaymentCreationFails_responseObjectContainsViolations(): void {
		$request = $this->newMinimumDonationRequest();
		$expectedViolation = new ConstraintViolation( $request->getPaymentCreationRequest(), 'payment_not_supported', 'payment' );
		$useCase = $this->makeUseCase( paymentService: $this->makeFailingPaymentService( 'payment_not_supported' ) );

		$result = $useCase->addDonation( $request );

		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals( [ $expectedViolation ], $result->getValidationErrors() );
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
		$mockNotifier = $this->createMock( DonationNotifier::class );
		$mockNotifier->expects( $this->never() )->method( 'sendConfirmationFor' );

		$useCase = $this->makeUseCase( notifier:  $mockNotifier );

		$useCase->addDonation( $this->newMinimumDonationRequest() );
	}

	public function testGivenValidRequest_confirmationEmailIsSent(): void {
		$donation = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$mockNotifier = $this->createMock( DonationNotifier::class );
		$mockNotifier->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $this->isInstanceOf( Donation::class ) );

		$useCase = $this->makeUseCase( notifier: $mockNotifier );

		$useCase->addDonation( $donation );
	}

	public function testGivenValidRequest_moderationEmailIsSent(): void {
		$mockNotifier = $this->createMock( DonationNotifier::class );

		$mockNotifier->expects( $this->once() )
			->method( 'sendModerationNotificationToAdmin' )
			->with( $this->isInstanceOf( Donation::class ) );

		$donation = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$useCase = $this->makeUseCase( notifier: $mockNotifier );

		$useCase->addDonation( $donation );
	}

	public function testGivenValidRequest_withIncompletePayment_confirmationEmailIsNotSent(): void {
		$paymentService = new SucceedingPaymentServiceStub( new PaymentCreationSucceeded(
			paymentId: 1,
			paymentProviderURLGenerator: new NullGenerator(),
			paymentComplete: false
		) );
		$mockNotifier = $this->createMock( DonationNotifier::class );
		$mockNotifier->expects( $this->never() )->method( 'sendConfirmationFor' );

		$useCase = $this->makeUseCase( notifier: $mockNotifier, paymentService: $paymentService );

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$useCase->addDonation( $request );
	}

	public function testGivenValidRequestWithPolicyViolation_donationIsModerated(): void {
		$useCase = $this->makeUseCase( policyValidator: $this->makeFakeFailingModerationService() );

		$response = $useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );
		$donation = $response->getDonation();

		$this->assertNotNull( $donation );
		$this->assertTrue( $donation->isMarkedForModeration() );
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

	public function testSuccessResponseContainsTokens(): void {
		$returnedTokens = new DonationTokens( 'a110-acce55', 'a110-00d8e' );
		$useCase = $this->makeUseCase(
			tokenFetcher: $this->makeFakeTokenFetcher( $returnedTokens )
		);

		$response = $useCase->addDonation( $this->newMinimumDonationRequest() );

		$this->assertSame( 'a110-acce55', $response->getAccessToken() );
		$this->assertSame( 'a110-00d8e', $response->getUpdateToken() );
	}

	public function testSuccessResponseContainsGeneratedUrl(): void {
		$urlGeneratorStub = $this->createStub( PaymentProviderURLGenerator::class );
		$urlGeneratorStub->method( 'generateURL' )->willReturn( self::PAYMENT_PROVIDER_URL );
		$useCase = $this->makeUseCase(
			paymentService: $this->makeSuccessfulPaymentServiceWithUrlGenerator( $urlGeneratorStub )
		);

		$response = $useCase->addDonation( $this->newMinimumDonationRequest() );

		$this->assertSame( self::PAYMENT_PROVIDER_URL, $response->getPaymentProviderRedirectUrl() );
	}

	public function testUrlGeneratorGetsDonationData(): void {
		$urlGenerator = new UrlGeneratorSpy();
		$useCase = $this->makeUseCase(
			idGenerator: new StaticDonationIdRepository(),
			paymentService: $this->makeSuccessfulPaymentServiceWithUrlGenerator( $urlGenerator )
		);

		$response = $useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'irrelevant@example.com' ) );

		$context = $urlGenerator->getLastContext();
		$this->assertSame( StaticDonationIdRepository::DONATION_ID, $context->itemId );
		$this->assertSame( 'D' . StaticDonationIdRepository::DONATION_ID, $context->invoiceId );
		$this->assertSame( $response->getAccessToken(), $context->accessToken );
		$this->assertSame( $response->getUpdateToken(), $context->updateToken );
		$this->assertSame( ValidDonation::DONOR_FIRST_NAME, $context->firstName );
		$this->assertSame( ValidDonation::DONOR_LAST_NAME, $context->lastName );
	}

	public function testGivenAnonymousDonation_UrlGeneratorGetsEmptyNames(): void {
		$urlGenerator = new UrlGeneratorSpy();
		$useCase = $this->makeUseCase(
			paymentService: $this->makeSuccessfulPaymentServiceWithUrlGenerator( $urlGenerator )
		);

		$useCase->addDonation( $this->newMinimumDonationRequest() );

		$context = $urlGenerator->getLastContext();
		$this->assertSame( '', $context->firstName );
		$this->assertSame( '', $context->lastName );
	}

	/**
	 * TODO move 'covers' tag for DonationCreatedEvent here when we've improved the PHPCS definitions
	 */
	public function testEventIsEmittedAfterDonationWasStored(): void {
		$eventEmitter = new EventEmitterSpy();
		$useCase = $this->makeUseCase( eventEmitter: $eventEmitter );

		$useCase->addDonation( $this->newValidCompanyDonationRequest() );

		/** @var DonationCreatedEvent[] $events */
		$events = $eventEmitter->getEvents();
		$this->assertCount( 1, $events, 'Only 1 event should be emitted' );
		$this->assertInstanceOf( DonationCreatedEvent::class, $events[0] );
		$this->assertInstanceOf( CompanyContactName::class, $events[0]->getDonor()->getName() );
	}

	public function testWhenEmailAddressIsBlacklisted_donationIsMarkedAsCancelled(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase(
			idGenerator: new StaticDonationIdRepository(),
			repository: $repository,
			policyValidator: $this->makeFakeAutodeletingPolicyValidator()
		);

		$useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );
		$donation = $repository->getDonationById( StaticDonationIdRepository::DONATION_ID );

		$this->assertNotNull( $donation );
		$this->assertTrue( $donation->isCancelled() );
	}

	public function testOptingIntoDonationReceipt_persistedInDonor(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase(
			idGenerator: new StaticDonationIdRepository(),
			repository: $repository
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( true );

		$useCase->addDonation( $request );
		$donation = $repository->getDonationById( StaticDonationIdRepository::DONATION_ID );

		$this->assertNotNull( $donation );
		$this->assertTrue( $donation->getDonor()->wantsReceipt() );
	}

	public function testOptingOutOfDonationReceipt_persistedInDonor(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase(
			idGenerator: new StaticDonationIdRepository(),
			repository: $repository
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( false );

		$useCase->addDonation( $request );
		$donation = $repository->getDonationById( StaticDonationIdRepository::DONATION_ID );

		$this->assertNotNull( $donation );
		$this->assertFalse( $donation->getDonor()->wantsReceipt() );
	}

	public function testOptingIntoNewsletter_persistedInDonor(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase(
			idGenerator: new StaticDonationIdRepository(),
			repository: $repository
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoNewsletter( true );

		$useCase->addDonation( $request );
		$donation = $repository->getDonationById( StaticDonationIdRepository::DONATION_ID );

		$this->assertNotNull( $donation );
		$this->assertTrue( $donation->getDonor()->wantsNewsletter() );
	}

	public function testOptingOutOfNewsletter_persistedInDonor(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase(
			idGenerator: new StaticDonationIdRepository(),
			repository: $repository
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoNewsletter( false );

		$useCase->addDonation( $request );
		$donation = $repository->getDonationById( StaticDonationIdRepository::DONATION_ID );

		$this->assertNotNull( $donation );
		$this->assertFalse( $donation->getDonor()->wantsNewsletter() );
	}

	private function makeUseCase(
		?DonationIdRepository $idGenerator = null,
		?DonationRepository $repository = null,
		?AddDonationValidator $donationValidator = null,
		?ModerationService $policyValidator = null,
		?DonationNotifier $notifier = null,
		?DonationTokenFetcher $tokenFetcher = null,
		?EventEmitter $eventEmitter = null,
		?CreatePaymentService $paymentService = null,
	): AddDonationUseCase {
		return new AddDonationUseCase(
			$idGenerator ?? new StaticDonationIdRepository(),
			$repository ?? $this->makeDonationRepositoryStub(),
			$donationValidator ?? $this->makeFakeSucceedingDonationValidator(),
			$policyValidator ?? $this->makeFakeSucceedingModerationService(),
			$notifier ?? $this->makeNotifierStub(),
			$tokenFetcher ?? $this->makeFakeTokenFetcher(),
			$eventEmitter ?? new EventEmitterSpy(),
			$paymentService ?? new SucceedingPaymentServiceStub()
		);
	}

	private function makeDonationRepositoryStub(): DonationRepository {
		return new FakeDonationRepository();
	}

	private function makeFakeSucceedingDonationValidator(): AddDonationValidator {
		$validator = $this->createStub( AddDonationValidator::class );
		$validator->method( 'validate' )->willReturn( new AddDonationValidationResult() );
		return $validator;
	}

	private function makeFakeFailingDonationValidator( ConstraintViolation $violation ): AddDonationValidator {
		$validator = $this->createStub( AddDonationValidator::class );
		$validator->method( 'validate' )->willReturn( new AddDonationValidationResult( $violation ) );
		return $validator;
	}

	private function makeFakeSucceedingModerationService(): ModerationService {
		$validator = $this->createStub( ModerationService::class );
		$validator->method( 'moderateDonationRequest' )->willReturn( new ModerationResult() );
		return $validator;
	}

	private function makeFakeFailingModerationService(): ModerationService {
		$result = new ModerationResult();
		$result->addModerationReason( new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN ) );
		$validator = $this->createStub( ModerationService::class );
		$validator->method( 'moderateDonationRequest' )->willReturn( $result );
		return $validator;
	}

	private function makeFakeAutodeletingPolicyValidator(): ModerationService {
		$validator = $this->createStub( ModerationService::class );
		$validator->method( 'moderateDonationRequest' )->willReturn( new ModerationResult() );
		$validator->method( 'isAutoDeleted' )->willReturn( true );
		return $validator;
	}

	private function makeNotifierStub(): DonationNotifier {
		return $this->createStub( DonationNotifier::class );
	}

	private function makeFakeTokenFetcher( ?DonationTokens $tokens = null ): DonationTokenFetcher {
		$tokens = $tokens ?? new DonationTokens(
				self::ACCESS_TOKEN,
				self::UPDATE_TOKEN
			);
		return new FixedDonationTokenFetcher( $tokens );
	}

	private function makeSuccessfulPaymentServiceWithUrlGenerator( PaymentProviderURLGenerator $urlGeneratorStub ): CreatePaymentService {
		$paymentService = $this->createStub( CreatePaymentService::class );
		$paymentService->method( 'createPayment' )->willReturn( new PaymentCreationSucceeded(
			1,
			$urlGeneratorStub,
			true
		) );
		return $paymentService;
	}

	private function makeFailingPaymentService( string $message ): CreatePaymentService {
		$paymentService = $this->createStub( CreatePaymentService::class );
		$paymentService->method( 'createPayment' )->willReturn( new PaymentCreationFailed( $message ) );
		return $paymentService;
	}

}
