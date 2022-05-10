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
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\CreatePaymentServiceSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\EventEmitterSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedDonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingPaymentServiceStub;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationPolicyValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\CreatePaymentService;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;
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
		$mockNotifier = $this->makeNotifierStub();
		$mockNotifier->expects( $this->never() )->method( $this->anything() );

		$useCase = $this->makeUseCase( notifier:  $mockNotifier );

		$useCase->addDonation( $this->newMinimumDonationRequest() );
	}

	public function testGivenValidRequest_confirmationEmailIsSent(): void {
		$mockNotifier = $this->makeNotifierStub();
		$mockNotifier->expects( $this->once() )
			->method( 'sendConfirmationFor' )
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
		$mockNotifier = $this->makeNotifierStub();
		$mockNotifier->expects( $this->never() )->method( $this->anything() );

		$useCase = $this->makeUseCase( notifier: $mockNotifier, paymentService: $paymentService );

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$useCase->addDonation( $request );
	}

	public function testGivenValidRequestWithPolicyViolation_donationIsModerated(): void {
		$useCase = $this->makeUseCase( policyValidator: $this->makeFakeFailingPolicyValidator() );

		$response = $useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );

		$this->assertTrue( $response->getDonation()->isMarkedForModeration() );
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
		$useCase = $this->makeUseCase();

		$response = $useCase->addDonation( $this->newMinimumDonationRequest() );

		$this->assertSame( self::UPDATE_TOKEN, $response->getUpdateToken() );
		$this->assertSame( self::ACCESS_TOKEN, $response->getAccessToken() );
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
		$this->assertInstanceOf( CompanyName::class, $events[0]->getDonor()->getName() );
	}

	public function testWhenEmailAddressIsBlacklisted_donationIsMarkedAsCancelled(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase( repository: $repository, policyValidator: $this->makeFakeAutodeletingPolicyValidator() );

		$useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );

		$this->assertTrue( $repository->getDonationById( 1 )->isCancelled() );
	}

	public function testOptingIntoDonationReceipt_persistedInDonation(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase( repository: $repository );

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( true );

		$useCase->addDonation( $request );

		$this->assertTrue( $repository->getDonationById( 1 )->getOptsIntoDonationReceipt() );
	}

	public function testOptingOutOfDonationReceipt_persistedInDonation(): void {
		$repository = $this->makeDonationRepositoryStub();
		$useCase = $this->makeUseCase( repository: $repository );

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( false );

		$useCase->addDonation( $request );

		$this->assertFalse( $repository->getDonationById( 1 )->getOptsIntoDonationReceipt() );
	}

	private function makeUseCase(
		?DonationRepository $repository = null,
		?AddDonationValidator $donationValidator = null,
		?AddDonationPolicyValidator $policyValidator = null,
		?DonationConfirmationNotifier $notifier = null,
		?DonationTokenFetcher $tokenFetcher = null,
		?EventEmitter $eventEmitter = null,
		?CreatePaymentService $paymentService = null,
	) {
		return new AddDonationUseCase(
			$repository ?? $this->makeDonationRepositoryStub(),
			$donationValidator ?? $this->makeFakeSucceedingDonationValidator(),
			$policyValidator ?? $this->makeFakeSucceedingPolicyValidator(),
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

	private function makeFakeSucceedingPolicyValidator(): AddDonationPolicyValidator {
		$validator = $this->createStub( AddDonationPolicyValidator::class );
		$validator->method( 'needsModeration' )->willReturn( false );
		return $validator;
	}

	private function makeFakeFailingPolicyValidator(): AddDonationPolicyValidator {
		$validator = $this->createStub( AddDonationPolicyValidator::class );
		$validator->method( 'needsModeration' )->willReturn( true );
		return $validator;
	}

	private function makeFakeAutodeletingPolicyValidator(): AddDonationPolicyValidator {
		$validator = $this->createStub( AddDonationPolicyValidator::class );
		$validator->method( 'needsModeration' )->willReturn( false );
		$validator->method( 'isAutoDeleted' )->willReturn( true );
		return $validator;
	}

	/**
	 * @return DonationConfirmationNotifier&MockObject
	 */
	private function makeNotifierStub(): DonationConfirmationNotifier {
		return $this->createStub( DonationConfirmationNotifier::class );
	}

	private function makeFakeTokenFetcher(): DonationTokenFetcher {
		return new FixedDonationTokenFetcher(
			new DonationTokens(
				self::ACCESS_TOKEN,
				self::UPDATE_TOKEN
			)
		);
	}

}
