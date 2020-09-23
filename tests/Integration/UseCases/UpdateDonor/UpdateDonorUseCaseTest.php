<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\UpdateDonor;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorRequest;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorResponse;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorUseCase;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidator;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorUseCase
 * @covers \WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorResponse
 * @covers \WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidationResult
 */
class UpdateDonorUseCaseTest extends TestCase {

	private TemplateBasedMailerSpy $templateMailer;

	protected function setUp(): void {
		$this->templateMailer = new TemplateBasedMailerSpy( $this );
	}

	public function testGivenAnonymousDonationAndValidAddressPersonalData_donationIsUpdated() {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );
		$donationId = $donation->getId();

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donationId ) );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertNotNull( $repository->getDonationById( $donationId )->getDonor() );
	}

	public function testGivenAnonymousDonationAndValidCompanyAddressData_donationIsUpdated() {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );
		$donationId = $donation->getId();

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForCompany( $donationId ) );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertFalse( $repository->getDonationById( $donationId )->donorIsAnonymous() );
	}

	public function testGivenAnonymousDonationAndValidAddressData_confirmationMailIsSent() {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );
		$donationId = $donation->getId();

		$useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donationId ) );

		$this->templateMailer->assertCalledOnce();
	}

	public function testGivenDonationWithAddressData_donationUpdateFails() {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newDirectDebitDonation();
		$repository->storeDonation( $donation );
		$donationId = $donation->getId();

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donationId ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( UpdateDonorResponse::ERROR_DONATION_HAS_ADDRESS, $response->getErrorMessage() );
	}

	public function testGivenFailingAuthorizer_donationUpdateFails() {
		$repository = $this->newRepository();
		$useCase = new UpdateDonorUseCase(
			new FailingDonationAuthorizer(),
			$this->newDonorValidator(),
			$repository,
			$this->newConfirmationMailer()
		);
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );
		$donationId = $donation->getId();

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donationId ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( UpdateDonorResponse::ERROR_ACCESS_DENIED, $response->getErrorMessage() );
	}

	public function testGivenExportedDonation_donationUpdateFails() {
		$repository = $this->newRepository();
		$useCase = $this->newUpdateDonorUseCase( $repository );
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$donation->markAsExported();
		$repository->storeDonation( $donation );
		$donationId = $donation->getId();

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donationId ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( UpdateDonorResponse::ERROR_DONATION_IS_EXPORTED, $response->getErrorMessage() );
	}

	public function testGivenFailingValidation_donationUpdateFails() {
		$repository = $this->newRepository();
		$validator = $this->createMock( UpdateDonorValidator::class );
		$validator->method( 'validateDonorData' )->willReturn(
			new UpdateDonorValidationResult( new ConstraintViolation( '', 'invalid_first_name', 'first_name' ) )
		);
		$useCase = new UpdateDonorUseCase(
			$this->newDonationAuthorizer(),
			$validator,
			$repository,
			$this->newConfirmationMailer()
		);
		$donation = ValidDonation::newIncompleteAnonymousPayPalDonation();
		$repository->storeDonation( $donation );
		$donationId = $donation->getId();

		$response = $useCase->updateDonor( $this->newUpdateDonorRequestForPerson( $donationId ) );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( 'donor_change_failure_validation_error', $response->getErrorMessage() );
	}

	private function newRepository(): DonationRepository {
		return new FakeDonationRepository();
	}

	private function newDonationAuthorizer(): DonationAuthorizer {
		return new SucceedingDonationAuthorizer();
	}

	private function newDonorValidator(): UpdateDonorValidator {
		$validator = $this->createMock( UpdateDonorValidator::class );
		$validator->method( 'validateDonorData' )->willReturn( new UpdateDonorValidationResult() );
		return $validator;
	}

	private function newUpdateDonorUseCase( DonationRepository $repository ): UpdateDonorUseCase {
		return new UpdateDonorUseCase(
			$this->newDonationAuthorizer(),
			$this->newDonorValidator(),
			$repository,
			$this->newConfirmationMailer()
		);
	}

	private function newUpdateDonorRequestForPerson( int $donationId ): UpdateDonorRequest {
		return ( new UpdateDonorRequest() )
			->withDonationId( $donationId )
			->withType( UpdateDonorRequest::TYPE_PERSON )
			->withFirstName( ValidDonation::DONOR_FIRST_NAME )
			->withLastName( ValidDonation::DONOR_LAST_NAME )
			->withSalutation( ValidDonation::DONOR_SALUTATION )
			->withTitle( '' )
			->withStreetAddress( ValidDonation::DONOR_STREET_ADDRESS )
			->withPostalCode( ValidDonation::DONOR_POSTAL_CODE )
			->withCity( ValidDonation::DONOR_CITY )
			->withCountryCode( ValidDonation::DONOR_COUNTRY_CODE )
			->withEmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS );
	}

	private function newUpdateDonorRequestForCompany( int $donationId ): UpdateDonorRequest {
		return ( new UpdateDonorRequest() )
			->withDonationId( $donationId )
			->withType( UpdateDonorRequest::TYPE_COMPANY )
			->withCompanyName( ValidDonation::DONOR_COMPANY )
			->withStreetAddress( ValidDonation::DONOR_STREET_ADDRESS )
			->withPostalCode( ValidDonation::DONOR_POSTAL_CODE )
			->withCity( ValidDonation::DONOR_CITY )
			->withCountryCode( ValidDonation::DONOR_COUNTRY_CODE )
			->withEmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS );
	}

	private function newConfirmationMailer(): DonationConfirmationMailer {
		return new DonationConfirmationMailer( $this->templateMailer );
	}

}
