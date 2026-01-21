<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\CreatePaymentServiceSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\EventEmitterSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\StaticDonationIdRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingPaymentServiceStub;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\CreatePaymentService;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Services\URLAuthenticator;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse as PaymentCreationFailed;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentParameters;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\SuccessResponse as PaymentCreationSucceeded;
use WMDE\FunValidators\ConstraintViolation;

#[CoversClass( AddDonationUseCase::class )]
#[CoversClass( DonationCreatedEvent::class )]
class AddDonationUseCaseTest extends TestCase {

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
		$expectedViolation = new ConstraintViolation( $request->getPaymentParameters(), 'payment_not_supported', 'payment' );
		$useCase = $this->makeUseCase( paymentService: $this->makeFailingPaymentService( 'payment_not_supported' ) );

		$result = $useCase->addDonation( $request );

		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals( [ $expectedViolation ], $result->getValidationErrors() );
	}

	private function newMinimumDonationRequest(): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setPaymentParameters( new PaymentParameters(
			100,
			PaymentInterval::OneTime->value,
			'UEB'
		) );
		$donationRequest->setDonorType( DonorType::ANONYMOUS );
		return $donationRequest;
	}

	private function newInvalidDonationRequest(): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setPaymentParameters( new PaymentParameters(
			100,
			PaymentInterval::OneTime->value,
			'BEZ'
		) );
		$donationRequest->setDonorType( DonorType::ANONYMOUS );
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
			paymentCompletionUrl: '',
			paymentComplete: false
		) );
		$mockNotifier = $this->createMock( DonationNotifier::class );
		$mockNotifier->expects( $this->never() )->method( 'sendConfirmationFor' );

		$useCase = $this->makeUseCase( notifier: $mockNotifier, paymentService: $paymentService );

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$useCase->addDonation( $request );
	}

	public function testGivenValidRequest_withBlockedEmail_confirmationEmailIsNotSent(): void {
		$mockNotifier = $this->createMock( DonationNotifier::class );
		$mockNotifier->expects( $this->never() )->method( 'sendConfirmationFor' );

		$useCase = $this->makeUseCase(
			policyValidator: $this->makeEmailBlockedModerationService(),
			notifier: $mockNotifier
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$useCase->addDonation( $request );
	}

	public function testGivenValidRequestWithPolicyViolation_donationIsModerated(): void {
		$useCase = $this->makeUseCase( policyValidator: $this->makeFakeFailingModerationService() );

		$response = $useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );
		$donation = $response->getDonation();

		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	private function newValidAddDonationRequestWithEmail( string $email ): AddDonationRequest {
		$request = $this->newMinimumDonationRequest();

		$request->setDonorType( DonorType::PERSON );
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

		$request->setDonorType( DonorType::COMPANY );
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

	public function testUrlAuthenticatorIsPassedToPaymentParameters(): void {
		$urlAuthenticator = $this->makeUrlAuthenticatorStub();
		$donationAuthorizer = $this->makeDonationAuthorizerStub( $urlAuthenticator );
		$paymentService = new CreatePaymentServiceSpy();
		$useCase = $this->makeUseCase(
			donationAuthorizer: $donationAuthorizer,
			paymentService: $paymentService
		);

		$useCase->addDonation( $this->newMinimumDonationRequest() );

		$lastRequest = $paymentService->getLastRequest();
		$this->assertSame( $urlAuthenticator, $lastRequest->urlAuthenticator );
	}

	public function testSuccessResponseContainsGeneratedUrl(): void {
		$useCase = $this->makeUseCase(
			paymentService: $this->makeSuccessfulPaymentServiceWithUrl()
		);

		$response = $useCase->addDonation( $this->newMinimumDonationRequest() );

		$this->assertSame( self::PAYMENT_PROVIDER_URL, $response->getPaymentCompletionUrl() );
	}

	public function testUrlGeneratorGetsDonationData(): void {
		$paymentService = new CreatePaymentServiceSpy();
		$useCase = $this->makeUseCase(
			idGenerator: new StaticDonationIdRepository(),
			paymentService: $paymentService
		);

		$useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'irrelevant@example.com' ) );

		$context = $paymentService->getLastRequest()->domainSpecificContext;
		$this->assertSame( StaticDonationIdRepository::DONATION_ID, $context->itemId );
		$this->assertSame( 'D' . StaticDonationIdRepository::DONATION_ID, $context->invoiceId );
		$this->assertSame( ValidDonation::DONOR_FIRST_NAME, $context->firstName );
		$this->assertSame( ValidDonation::DONOR_LAST_NAME, $context->lastName );
	}

	public function testGivenAnonymousDonation_UrlGeneratorGetsEmptyNames(): void {
		$paymentService = new CreatePaymentServiceSpy();
		$useCase = $this->makeUseCase(
			paymentService: $paymentService
		);

		$useCase->addDonation( $this->newMinimumDonationRequest() );

		$context = $paymentService->getLastRequest()->domainSpecificContext;
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
		/** @phpstan-ignore-next-line method.alreadyNarrowedType */
		$this->assertInstanceOf( DonationCreatedEvent::class, $events[0] );
		$this->assertInstanceOf( CompanyContactName::class, $events[0]->getDonor()->getName() );
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
		$this->assertTrue( $donation->getDonor()->isSubscribedToMailingList() );
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
		$this->assertFalse( $donation->getDonor()->isSubscribedToMailingList() );
	}

	private function makeUseCase(
		?DonationIdRepository $idGenerator = null,
		?DonationRepository $repository = null,
		?AddDonationValidator $donationValidator = null,
		?ModerationService $policyValidator = null,
		?DonationNotifier $notifier = null,
		?DonationAuthorizer $donationAuthorizer = null,
		?EventEmitter $eventEmitter = null,
		?CreatePaymentService $paymentService = null,
	): AddDonationUseCase {
		return new AddDonationUseCase(
			$idGenerator ?? new StaticDonationIdRepository(),
			$repository ?? $this->makeDonationRepositoryStub(),
			$donationValidator ?? $this->makeFakeSucceedingDonationValidator(),
			$policyValidator ?? $this->makeFakeSucceedingModerationService(),
			$notifier ?? $this->makeNotifierStub(),
			$donationAuthorizer ?? $this->makeDonationAuthorizerStub(),
			$eventEmitter ?? new EventEmitterSpy(),
			$paymentService ?? new SucceedingPaymentServiceStub()
		);
	}

	private function makeDonationRepositoryStub(): DonationRepository {
		return new FakeDonationRepository();
	}

	private function makeFakeSucceedingDonationValidator(): AddDonationValidator {
		return $this->createConfiguredStub(
			AddDonationValidator::class,
			[ 'validate' => new AddDonationValidationResult() ]
		);
	}

	private function makeFakeFailingDonationValidator( ConstraintViolation $violation ): AddDonationValidator {
		return $this->createConfiguredStub(
			AddDonationValidator::class,
			[ 'validate' => new AddDonationValidationResult( $violation ) ]
		);
	}

	private function makeFakeSucceedingModerationService(): ModerationService {
		return $this->createConfiguredStub(
			ModerationService::class,
			[ 'moderateDonationRequest' => new ModerationResult() ]
		);
	}

	private function makeFakeFailingModerationService(): ModerationService {
		$result = new ModerationResult();
		$result->addModerationReason( new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN ) );
		return $this->createConfiguredStub(
			ModerationService::class,
			[ 'moderateDonationRequest' => $result ]
		);
	}

	private function makeEmailBlockedModerationService(): ModerationService {
		$result = new ModerationResult();
		$result->addModerationReason( new ModerationReason( ModerationIdentifier::EMAIL_BLOCKED ) );
		return $this->createConfiguredStub(
			ModerationService::class,
			[ 'moderateDonationRequest' => $result ]
		);
	}

	private function makeNotifierStub(): DonationNotifier {
		return $this->createStub( DonationNotifier::class );
	}

	private function makeSuccessfulPaymentServiceWithUrl(): CreatePaymentService {
		return $this->createConfiguredStub(
			CreatePaymentService::class,
			[ 'createPayment' => new PaymentCreationSucceeded(
				1,
				self::PAYMENT_PROVIDER_URL,
				true
			) ]
		);
	}

	private function makeFailingPaymentService( string $message ): CreatePaymentService {
		return $this->createConfiguredStub(
			CreatePaymentService::class,
			[ 'createPayment' => new PaymentCreationFailed( $message ) ]
		);
	}

	private function makeDonationAuthorizerStub( ?URLAuthenticator $authenticator = null ): DonationAuthorizer {
		return $this->createConfiguredStub(
			DonationAuthorizer::class,
			[ 'authorizeDonationAccess' => $authenticator ?? $this->makeUrlAuthenticatorStub() ]
		);
	}

	private function makeUrlAuthenticatorStub(): URLAuthenticator {
		$authenticator = $this->createConfiguredStub(
			URLAuthenticator::class,
			[ 'getAuthenticationTokensForPaymentProviderUrl' => [] ]
		);
		$authenticator->method( 'addAuthenticationTokensToApplicationUrl' )->willReturnArgument( 0 );
		return $authenticator;
	}

}
