<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokens;
use WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\EventEmitterSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeEventEmitter;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedDonationTokenFetcher;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationPolicyValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationUseCase;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\InitialDonationStatusPicker;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\ReferrerGeneralizer;
use WMDE\Fundraising\PaymentContext\Domain\LessSimpleTransferCodeGenerator;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\TransferCodeGenerator;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationUseCase
 * @covers \WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent
 *
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class AddDonationUseCaseTest extends TestCase {

	private const UPDATE_TOKEN = 'a very nice token';
	private const ACCESS_TOKEN = 'kindly allow me access';

	/**
	 * @var \DateTime
	 */
	private $oneHourInTheFuture;

	public function setUp(): void {
		$this->oneHourInTheFuture = ( new \DateTime() )->add( $this->newOneHourInterval() );
	}

	public function testWhenValidationSucceeds_successResponseIsCreated(): void {
		$useCase = $this->newValidationSucceedingUseCase();

		$this->assertTrue( $useCase->addDonation( $this->newMinimumDonationRequest() )->isSuccessful() );
	}

	public function testWhenAnonymousDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$useCase = $this->newActiveBankTransferCodeGeneratorUseCase();
		$donationRequest = $this->newMinimumDonationRequest();

		$response = $useCase->addDonation( $donationRequest );

		self::assertStringStartsWith( 'XR', $response->getDonation()->getPaymentMethod()->getBankTransferCode() );
	}

	public function testWhenPrivateDonationIsMade_correctBankTransferPrefixIsAdded(): void {
		$useCase = $this->newActiveBankTransferCodeGeneratorUseCase();
		$donationRequest = $this->newValidAddDonationRequestWithEmail( 'bill.gates@wikimedia.de' );

		$response = $useCase->addDonation( $donationRequest );

		self::assertStringStartsWith( 'XW', $response->getDonation()->getPaymentMethod()->getBankTransferCode() );
	}

	public function testWhenCompanyDonationIsMade_correctBankTransferPrefixIsAdded(): void {
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
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new EventEmitterSpy()
		);
	}

	private function newActiveBankTransferCodeGeneratorUseCase(): AddDonationUseCase {
		return new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			LessSimpleTransferCodeGenerator::newRandomGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);
	}

	/**
	 * @return DonationConfirmationMailer|MockObject
	 */
	private function newMailer(): DonationConfirmationMailer {
		return $this->getMockBuilder( DonationConfirmationMailer::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function newTokenFetcher(): DonationTokenFetcher {
		return new FixedDonationTokenFetcher(
			new DonationTokens(
				self::ACCESS_TOKEN,
				self::UPDATE_TOKEN
			)
		);
	}

	private function newOneHourInterval(): \DateInterval {
		return new \DateInterval( 'PT1H' );
	}

	private function newRepository(): DonationRepository {
		return new FakeDonationRepository();
	}

	public function testValidationFails_responseObjectContainsViolations(): void {
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);

		$result = $useCase->addDonation( $this->newMinimumDonationRequest() );
		$this->assertEquals( [ new ConstraintViolation( 'foo', 'bar' ) ], $result->getValidationErrors() );
	}

	public function testValidationFails_responseObjectContainsRequestObject(): void {
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
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

	private function getSucceedingPolicyValidatorMock(): AddDonationPolicyValidator {
		$validator = $this->getMockBuilder( AddDonationPolicyValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$validator->method( 'needsModeration' )->willReturn( false );

		return $validator;
	}

	private function getFailingPolicyValidatorMock(): AddDonationPolicyValidator {
		$validator = $this->getMockBuilder( AddDonationPolicyValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$validator->method( 'needsModeration' )->willReturn( true );

		return $validator;
	}

	private function getAutoDeletingPolicyValidatorMock(): AddDonationPolicyValidator {
		$validator = $this->getMockBuilder( AddDonationPolicyValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$validator->method( 'isAutoDeleted' )->willReturn( true );

		return $validator;
	}

	private function newMinimumDonationRequest(): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setAmount( Euro::newFromString( '1.00' ) );
		$donationRequest->setPaymentType( PaymentMethod::BANK_TRANSFER );
		$donationRequest->setDonorType( DonorType::ANONYMOUS() );
		return $donationRequest;
	}

	private function newInvalidDonationRequest(): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setPaymentType( PaymentMethod::DIRECT_DEBIT );
		$donationRequest->setAmount( Euro::newFromInt( 0 ) );
		$donationRequest->setDonorType( DonorType::ANONYMOUS() );
		return $donationRequest;
	}

	public function testGivenInvalidRequest_noConfirmationEmailIsSend(): void {
		$mailer = $this->newMailer();

		$mailer->expects( $this->never() )->method( $this->anything() );

		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			$this->getSucceedingPolicyValidatorMock(),
			$mailer,
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);

		$useCase->addDonation( $this->newMinimumDonationRequest() );
	}

	private function newTransferCodeGenerator(): TransferCodeGenerator {
		return $this->createMock( TransferCodeGenerator::class );
	}

	public function testGivenValidRequest_confirmationEmailIsSent(): void {
		$mailer = $this->newMailer();
		$donation = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );

		$mailer->expects( $this->once() )
			->method( 'sendConfirmationMailFor' )
			->with( $this->isInstanceOf( Donation::class ) );

		$useCase = $this->newUseCaseWithMailer( $mailer );

		$useCase->addDonation( $donation );
	}

	public function testGivenValidRequestWithExternalPaymentType_confirmationEmailIsNotSent(): void {
		$mailer = $this->newMailer();

		$mailer->expects( $this->never() )->method( $this->anything() );

		$useCase = $this->newUseCaseWithMailer( $mailer );

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setPaymentType( 'PPL' );
		$useCase->addDonation( $request );
	}

	public function testGivenValidRequestWithPolicyViolation_donationIsModerated(): void {
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getFailingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);

		$response = $useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );
		$this->assertTrue( $response->getDonation()->isMarkedForModeration() );
	}

	public function testGivenPolicyViolationForExternalPaymentDonation_donationIsNotModerated(): void {
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getFailingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setPaymentType( 'PPL' );
		$response = $useCase->addDonation( $request );
		$this->assertFalse( $response->getDonation()->isMarkedForModeration() );
	}

	private function newUseCaseWithMailer( DonationConfirmationMailer $mailer ): AddDonationUseCase {
		return new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getSucceedingPolicyValidatorMock(),
			$mailer,
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
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
		$useCase = $this->newValidationSucceedingUseCase();

		$response = $useCase->addDonation( $this->newMinimumDonationRequest() );

		$this->assertSame( self::UPDATE_TOKEN, $response->getUpdateToken() );
		$this->assertSame( self::ACCESS_TOKEN, $response->getAccessToken() );
	}

	/**
	 * TODO move 'covers' tag for DonationCreatedEvent here when we've improved the PHPCS definitions
	 */
	public function testWhenValidationSucceeds_eventIsEmitted(): void {
		$eventEmitter = new EventEmitterSpy();
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			$this->getSucceedingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
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
		$repository = $this->newRepository();
		$useCase = new AddDonationUseCase(
			$repository,
			$this->getSucceedingValidatorMock(),
			$this->getAutoDeletingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);

		$useCase->addDonation( $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' ) );
		$this->assertTrue( $repository->getDonationById( 1 )->isCancelled() );
	}

	public function testOptingIntoDonationReceipt_persistedInDonation(): void {
		$repository = $this->newRepository();
		$useCase = new AddDonationUseCase(
			$repository,
			$this->getSucceedingValidatorMock(),
			$this->getAutoDeletingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( true );

		$useCase->addDonation( $request );

		$this->assertSame( true, $repository->getDonationById( 1 )->getOptsIntoDonationReceipt() );
	}

	public function testOptingOutOfDonationReceipt_persistedInDonation(): void {
		$repository = $this->newRepository();
		$useCase = new AddDonationUseCase(
			$repository,
			$this->getSucceedingValidatorMock(),
			$this->getAutoDeletingPolicyValidatorMock(),
			$this->newMailer(),
			$this->newTransferCodeGenerator(),
			$this->newTokenFetcher(),
			new InitialDonationStatusPicker(),
			new FakeEventEmitter()
		);

		$request = $this->newValidAddDonationRequestWithEmail( 'foo@bar.baz' );
		$request->setOptsIntoDonationReceipt( false );

		$useCase->addDonation( $request );

		$this->assertSame( false, $repository->getDonationById( 1 )->getOptsIntoDonationReceipt() );
	}

}
