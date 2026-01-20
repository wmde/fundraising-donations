<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\UpdateDonor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Event\DonorUpdatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\EventEmitterSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeEventEmitter;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorRequest;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorResponse;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorUseCase;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidator;
use WMDE\FunValidators\ConstraintViolation;

#[CoversClass( UpdateDonorUseCase::class )]
#[CoversClass( UpdateDonorResponse::class )]
#[CoversClass( UpdateDonorValidationResult::class )]
class UpdateDonorUseCaseTest extends TestCase {

	public function testGivenAnonymousDonationAndValidAddressPersonalData_donationIsUpdated(): void {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donation->getId() ) );
		$donation = $repository->getDonationById( $donation->getId() );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertNotNull( $donation );
		$donor = $donation->getDonor();
		$this->assertEquals(
			new PersonName(
				ValidDonation::DONOR_FIRST_NAME,
				ValidDonation::DONOR_LAST_NAME,
				ValidDonation::DONOR_SALUTATION,
				ValidDonation::DONOR_TITLE
			),
			$donor->getName()
		);
		$this->assertEquals(
			new PostalAddress(
				ValidDonation::DONOR_STREET_ADDRESS,
				ValidDonation::DONOR_POSTAL_CODE,
				ValidDonation::DONOR_CITY,
				ValidDonation::DONOR_COUNTRY_CODE
			),
			$donor->getPhysicalAddress()
		);
		$this->assertSame( ValidDonation::DONOR_EMAIL_ADDRESS, $donor->getEmailAddress() );
		$this->assertFalse( $donor->isSubscribedToMailingList() );
	}

	/**
	 * We don't test subscribing to mailingList with a Company, because the code should take the same path for both
	 * address types.
	 */
	public function testGivenAnonymousDonationAndUserAcceptsMailingList_donationIsUpdated(): void {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );
		$request = $this->newUpdateDonorRequestForPerson( $donation->getId() );
		$request = $request->acceptMailingList();

		$response = $useCase->updateDonor( $request );
		$donation = $repository->getDonationById( $donation->getId() );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertNotNull( $donation );
		$donor = $donation->getDonor();
		$this->assertTrue( $donor->isSubscribedToMailingList() );
	}

	public function testGivenAnonymousDonationAndValidCompanyAddressData_donationIsUpdated(): void {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForCompany( $donation->getId() ) );
		$donation = $repository->getDonationById( $donation->getId() );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertNotNull( $donation );
		$donor = $donation->getDonor();
		$this->assertEquals(
			new CompanyContactName(
				ValidDonation::DONOR_COMPANY,
				ValidDonation::DONOR_FIRST_NAME,
				ValidDonation::DONOR_LAST_NAME,
				ValidDonation::DONOR_SALUTATION,
				ValidDonation::DONOR_TITLE
			),
			$donor->getName()
		);
		$this->assertEquals(
			new PostalAddress(
				ValidDonation::DONOR_STREET_ADDRESS,
				ValidDonation::DONOR_POSTAL_CODE,
				ValidDonation::DONOR_CITY,
				ValidDonation::DONOR_COUNTRY_CODE
			),
			$donor->getPhysicalAddress()
		);
		$this->assertSame( ValidDonation::DONOR_EMAIL_ADDRESS, $donor->getEmailAddress() );
		$this->assertFalse( $donor->isSubscribedToMailingList() );
	}

	public function testGivenAnonymousDonationAndValidAddressData_confirmationMailIsSent(): void {
		$repository = $this->newRepository();
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();

		$mailer = $this->createMock( DonationNotifier::class );
		$mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $donation );

		$useCase = $this->newUpdateDonorUseCase( $repository, confirmationMailer: $mailer );

		$repository->storeDonation( $donation );

		$useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donation->getId() ) );
	}

	public function testGivenFailingAuthorizer_donationUpdateFails(): void {
		$repository = $this->newRepository();
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );

		$useCase = $this->newUpdateDonorUseCase( $repository, donationAuthorizer: new FailingDonationAuthorizer() );

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donation->getId() ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( UpdateDonorResponse::ERROR_ACCESS_DENIED, $response->getErrorMessage() );
	}

	public function testDonationNotFound_donationUpdateFails(): void {
		$repository = $this->newRepository();

		$useCase = $this->newUpdateDonorUseCase( $repository );

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( 1 ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( UpdateDonorResponse::ERROR_DONATION_NOT_FOUND, $response->getErrorMessage() );
	}

	public function testGivenExportedDonation_donationUpdateFails(): void {
		$repository = $this->newRepository();
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$donation->markAsExported();
		$repository->storeDonation( $donation );

		$useCase = $this->newUpdateDonorUseCase( $repository );

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donation->getId() ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( UpdateDonorResponse::ERROR_DONATION_IS_EXPORTED, $response->getErrorMessage() );
	}

	public function testGivenCanceledDonation_donationUpdateFails(): void {
		$repository = $this->newRepository();
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->setDonor( new AnonymousDonor() );
		$donation->cancel();
		$repository->storeDonation( $donation );

		$useCase = $this->newUpdateDonorUseCase( $repository );

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donation->getId() ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( UpdateDonorResponse::ERROR_ACCESS_DENIED, $response->getErrorMessage() );
	}

	public function testGivenFailingValidation_donationUpdateFails(): void {
		$repository = $this->newRepository();
		$validator = $this->createConfiguredStub(
			UpdateDonorValidator::class,
			[ 'validateDonorData' => new UpdateDonorValidationResult( new ConstraintViolation( '', 'invalid_first_name', 'first_name' ) ) ]
		);
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );

		$useCase = $this->newUpdateDonorUseCase( $repository, donorValidator: $validator );

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donation->getId() ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( 'donor_change_failure_validation_error', $response->getErrorMessage() );
	}

	public function testOnUpdateAddress_emitsEvent(): void {
		$repository = $this->newRepository();
		$eventEmitter = new EventEmitterSpy();
		$donation = ValidDonation::newBookedAnonymousPayPalDonation();
		$repository->storeDonation( $donation );
		$previousDonor = $donation->getDonor();
		$updateDonorRequest = $this->newUpdateDonorRequestForPerson( $donation->getId() );

		$useCase = $this->newUpdateDonorUseCase( $repository, eventEmitter: $eventEmitter );
		$useCase->updateDonor( $updateDonorRequest );

		/** @var DonorUpdatedEvent[] $events */
		$events = $eventEmitter->getEvents();

		$this->assertCount( 1, $events, 'Only 1 event should be emitted' );
		/** @phpstan-ignore-next-line method.alreadyNarrowedType */
		$this->assertInstanceOf( DonorUpdatedEvent::class, $events[0] );
		$this->assertSame( $donation->getId(), $events[0]->getDonationId() );
		$this->assertSame( $previousDonor->getName(), $events[0]->getPreviousDonor()->getName() );
		$this->assertStringContainsString( $updateDonorRequest->getFirstName(), $events[0]->getNewDonor()->getName()->getFullName() );
		$this->assertStringContainsString( $updateDonorRequest->getLastName(), $events[0]->getNewDonor()->getName()->getFullName() );
		$this->assertNotSame( $events[0]->getPreviousDonor(), $events[0]->getNewDonor(), 'Event should contain a new donor instance' );
	}

	private function newRepository(): DonationRepository {
		return new FakeDonationRepository();
	}

	private function newDonationAuthorizer(): DonationAuthorizationChecker {
		return new SucceedingDonationAuthorizer();
	}

	private function newDonorValidator(): UpdateDonorValidator {
		return $this->createConfiguredStub(
			UpdateDonorValidator::class,
			[ 'validateDonorData' => new UpdateDonorValidationResult() ]
		);
	}

	private function newUpdateDonorUseCase( DonationRepository $repository, ?DonationNotifier $confirmationMailer = null, ?EventEmitter $eventEmitter = null, ?UpdateDonorValidator $donorValidator = null, ?DonationAuthorizationChecker $donationAuthorizer = null ): UpdateDonorUseCase {
		return new UpdateDonorUseCase(
			$donationAuthorizer ?? $this->newDonationAuthorizer(),
			$donorValidator ?? $this->newDonorValidator(),
			$repository,
			$confirmationMailer ?? $this->newConfirmationMailer(),
			$eventEmitter ?? new FakeEventEmitter()
		);
	}

	private function newUpdateDonorRequestForPerson( int $donationId ): UpdateDonorRequest {
		return ( new UpdateDonorRequest() )
			->withDonationId( $donationId )
			->withType( DonorType::PERSON )
			->withFirstName( ValidDonation::DONOR_FIRST_NAME )
			->withLastName( ValidDonation::DONOR_LAST_NAME )
			->withSalutation( ValidDonation::DONOR_SALUTATION )
			->withTitle( ValidDonation::DONOR_TITLE )
			->withStreetAddress( ValidDonation::DONOR_STREET_ADDRESS )
			->withPostalCode( ValidDonation::DONOR_POSTAL_CODE )
			->withCity( ValidDonation::DONOR_CITY )
			->withCountryCode( ValidDonation::DONOR_COUNTRY_CODE )
			->withEmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS )
			->declineMailingList();
	}

	private function newUpdateDonorRequestForCompany( int $donationId ): UpdateDonorRequest {
		return ( new UpdateDonorRequest() )
			->withDonationId( $donationId )
			->withType( DonorType::COMPANY )
			->withCompanyName( ValidDonation::DONOR_COMPANY )
			->withFirstName( ValidDonation::DONOR_FIRST_NAME )
			->withLastName( ValidDonation::DONOR_LAST_NAME )
			->withSalutation( ValidDonation::DONOR_SALUTATION )
			->withTitle( ValidDonation::DONOR_TITLE )
			->withStreetAddress( ValidDonation::DONOR_STREET_ADDRESS )
			->withPostalCode( ValidDonation::DONOR_POSTAL_CODE )
			->withCity( ValidDonation::DONOR_CITY )
			->withCountryCode( ValidDonation::DONOR_COUNTRY_CODE )
			->withEmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS )
			->declineMailingList();
	}

	private function newConfirmationMailer(): DonationNotifier {
		return $this->createStub( DonationNotifier::class );
	}
}
