<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Integration\UseCases\ValidateDonor;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\Frontend\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ValidateDonor\ValidateDonorRequest;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ValidateDonor\ValidateDonorResponse;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ValidateDonor\ValidateDonorUseCase;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @covers \WMDE\Fundraising\Frontend\DonationContext\UseCases\ValidateDonor\ValidateDonorUseCase
 *
 * @license GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ValidateDonorUseCaseTest extends TestCase {

	/**
	 * @var ValidateDonorUseCase
	 */
	private $donorValidator;

	public function setUp(): void {
		$this->donorValidator = $this->newValidateDonorUseCase();
	}

	private function newValidateDonorUseCase(): ValidateDonorUseCase {
		return new ValidateDonorUseCase( $this->newSucceedingEmailValidator() );
	}

	private function newSucceedingEmailValidator(): EmailValidator {
		$validator = $this->createMock( EmailValidator::class );

		$validator->expects( $this->any() )
			->method( $this->anything() )
			->willReturn( new ValidationResult() );

		return $validator;
	}

	public function testGivenValidDonor_validationIsSuccessful() {
		$this->assertPassesValidation( $this->getValidRequestModel() );
	}

	private function assertPassesValidation( ValidateDonorRequest $requestModel ) {
		$this->assertEmpty( $this->donorValidator->validateDonor( $requestModel )->getViolations() );
	}

	private function getValidRequestModel(): ValidateDonorRequest {
		return ValidateDonorRequest::newInstance()
			->withCity( ValidDonation::DONOR_CITY )
			->withCompanyName( '' )
			->withCountryCode( ValidDonation::DONOR_COUNTRY_CODE )
			->withEmailAddress( ValidDonation::DONOR_EMAIL_ADDRESS )
			->withFirstName( ValidDonation::DONOR_FIRST_NAME )
			->withLastName( ValidDonation::DONOR_LAST_NAME )
			->withPostalCode( ValidDonation::DONOR_POSTAL_CODE )
			->withSalutation( ValidDonation::DONOR_SALUTATION )
			->withStreetAddress( ValidDonation::DONOR_STREET_ADDRESS )
			->withTitle( ValidDonation::DONOR_TITLE )
			->withType( ValidateDonorRequest::PERSON_PRIVATE );
	}

	public function testCompanyDonorWithoutCompanyNameFailsValidation() {
		$requestModel = $this->getValidRequestModel()
			->withType( ValidateDonorRequest::PERSON_COMPANY )
			->withCompanyName( '' );

		$this->assertConstraintWasViolated(
			$this->donorValidator->validateDonor( $requestModel ),
			ValidateDonorResponse::SOURCE_COMPANY
		);
	}

	private function assertConstraintWasViolated( ValidationResult $result, string $fieldName ): void {
		$this->assertContainsOnlyInstancesOf( ConstraintViolation::class, $result->getViolations() );
		$this->assertTrue( $result->hasViolations() );

		$violated = false;
		foreach ( $result->getViolations() as $violation ) {
			if ( $violation->getSource() === $fieldName ) {
				$violated = true;
			}
		}

		$this->assertTrue(
			$violated,
			'Failed asserting that constraint for field "' . $fieldName . '"" was violated.'
		);
	}

	public function testCompanyDonorWithCompanyNamePassesValidation() {
		$requestModel = $this->getValidRequestModel()
			->withType( ValidateDonorRequest::PERSON_COMPANY )
			->withCompanyName( 'Such Company' );

		$this->assertPassesValidation( $requestModel );
	}

	public function testPersonalInfoWithLongFields_validationFails(): void {
		$longText = str_repeat( 'Cats ', 500 );

		$requestModel = ValidateDonorRequest::newInstance()
			->withCity( $longText )
			->withCompanyName( $longText )
			->withCountryCode( $longText )
			->withEmailAddress( $longText . '@example.com' )
			->withFirstName( $longText )
			->withLastName( $longText )
			->withPostalCode( $longText )
			->withSalutation( $longText )
			->withStreetAddress( $longText )
			->withTitle( $longText )
			->withType( $longText );

		$result = $this->donorValidator->validateDonor( $requestModel );

		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_FIRST_NAME );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_LAST_NAME );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_SALUTATION );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_TITLE );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_STREET_ADDRESS );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_CITY );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_POSTAL_CODE );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_COUNTRY );
		$this->assertConstraintWasViolated( $result, ValidateDonorResponse::SOURCE_EMAIL );
	}

}
