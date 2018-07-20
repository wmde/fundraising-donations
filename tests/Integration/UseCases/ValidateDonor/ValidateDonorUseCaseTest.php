<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\ValidateDonor;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\UseCases\ValidateDonor\ValidateDonorRequest;
use WMDE\Fundraising\DonationContext\UseCases\ValidateDonor\ValidateDonorResponse;
use WMDE\Fundraising\DonationContext\UseCases\ValidateDonor\ValidateDonorUseCase;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\ValidateDonor\ValidateDonorUseCase
 * @covers \WMDE\Fundraising\DonationContext\Domain\Validation\DonorValidator
 *
 * @license GNU GPL v2+
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
			->withType( DonorName::PERSON_PRIVATE );
	}

	public function testCompanyDonorWithoutCompanyNameFailsValidation() {
		$requestModel = $this->getValidRequestModel()
			->withType( DonorName::PERSON_COMPANY )
			->withCompanyName( '' );

		$this->assertConstraintWasViolated(
			$this->donorValidator->validateDonor( $requestModel ),
			ValidateDonorResponse::SOURCE_COMPANY
		);
	}

	private function assertConstraintWasViolated( ValidationResult $result, string $fieldName, string $violationType = null ): void {
		$this->assertContainsOnlyInstancesOf( ConstraintViolation::class, $result->getViolations() );

		foreach ( $result->getViolations() as $violation ) {
			if ( $violation->getSource() === $fieldName ) {
				$this->assertTrue(
					$violationType === null || $violationType === $violation->getMessageIdentifier(),
					"Failed asserting that constraint for field \"$fieldName\" caused violation of type \"$violationType\"."
				);

				return;
			}
		}

		$this->assertTrue(
			false,
			'Failed asserting that constraint for field "' . $fieldName . '"" was violated.'
		);
	}

	public function testCompanyDonorWithCompanyNamePassesValidation() {
		$requestModel = $this->getValidRequestModel()
			->withType( DonorName::PERSON_COMPANY )
			->withCompanyName( 'Such Company' );

		$this->assertPassesValidation( $requestModel );
	}

	public function testTextValuesAreTooLong_validationFails(): void {
		$longText = str_repeat( 'Cats ', 500 );

		$requestModel = ValidateDonorRequest::newInstance()
			->withFirstName( $longText )
			->withLastName( $longText )
			->withSalutation( $longText )
			->withTitle( $longText )
			->withCity( $longText )
			->withCountryCode( $longText )
			->withEmailAddress( $longText . '@example.com' )
			->withPostalCode( $longText )
			->withStreetAddress( $longText )
			->withType( DonorName::PERSON_PRIVATE );

		$result = $this->donorValidator->validateDonor( $requestModel );

		foreach ( [
					  ValidateDonorResponse::SOURCE_FIRST_NAME,
					  ValidateDonorResponse::SOURCE_LAST_NAME,
					  ValidateDonorResponse::SOURCE_SALUTATION,
					  ValidateDonorResponse::SOURCE_TITLE,
					  ValidateDonorResponse::SOURCE_STREET_ADDRESS,
					  ValidateDonorResponse::SOURCE_CITY,
					  ValidateDonorResponse::SOURCE_POSTAL_CODE,
					  ValidateDonorResponse::SOURCE_COUNTRY,
					  ValidateDonorResponse::SOURCE_EMAIL,
				  ] as $fieldName ) {
			$this->assertConstraintWasViolated( $result, $fieldName, ValidateDonorResponse::VIOLATION_WRONG_LENGTH );
		}

		$result = $this->donorValidator->validateDonor( $requestModel
			->withType( DonorName::PERSON_COMPANY )
			->withCompanyName( $longText )
		);

		$this->assertConstraintWasViolated(
			$result,
			ValidateDonorResponse::SOURCE_COMPANY,
			ValidateDonorResponse::VIOLATION_WRONG_LENGTH
		);
	}

	/**
	 * @dataProvider invalidPostalCodeProvider
	 */
	public function testInvalidPostalCodeFailsValidation( string $invalidPostalCode ) {
		$requestModel = $this->getValidRequestModel()
			->withPostalCode( $invalidPostalCode );

		$this->assertConstraintWasViolated(
			$this->donorValidator->validateDonor( $requestModel ),
			ValidateDonorResponse::SOURCE_POSTAL_CODE,
			ValidateDonorResponse::VIOLATION_NOT_POSTCODE
		);
	}

	public function invalidPostalCodeProvider(): iterable {
		yield [ '1' ];
		yield [ '12' ];
		yield [ '123' ];
		yield [ '123456' ];
		yield [ '12 34' ];
		yield [ '1234a' ];
		yield [ 'aaaa' ];
	}

	public function testGivenValidPostCode_validationIsSuccessful() {
		$this->assertPassesValidation( $this->getValidRequestModel()->withPostalCode( '1234' ) );
		$this->assertPassesValidation( $this->getValidRequestModel()->withPostalCode( '12345' ) );
	}

	public function testGivenInvalidDonorType_validationFails() {
		$requestModel = $this->getValidRequestModel()
			->withType( 'Goat' );

		$this->assertConstraintWasViolated(
			$this->donorValidator->validateDonor( $requestModel ),
			ValidateDonorResponse::SOURCE_ADDRESS_TYPE,
			ValidateDonorResponse::VIOLATION_WRONG_TYPE
		);
	}

}
