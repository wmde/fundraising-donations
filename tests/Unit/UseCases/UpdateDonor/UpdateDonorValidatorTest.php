<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\UpdateDonor;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidatorPatterns;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorRequest;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidator;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\SucceedingDomainNameValidator;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AddressValidator;
use WMDE\FunValidators\Validators\EmailValidator;
use WMDE\FunValidators\Validators\SucceedingEmailValidator;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidator
 */
class UpdateDonorValidatorTest extends TestCase {

	public function testGivenAnonymousDonor_validationFails(): void {
		$validator = new UpdateDonorValidator(
			new AddressValidator( ValidatorPatterns::COUNTRY_POSTCODE, ValidatorPatterns::ADDRESS_PATTERNS ),
			new SucceedingEmailValidator()
		);
		$request = ( new UpdateDonorRequest() )->withType( DonorType::ANONYMOUS() );

		$result = $validator->validateDonorData( $request );

		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals(
			new ConstraintViolation(
				DonorType::ANONYMOUS(),
				UpdateDonorValidator::VIOLATION_ANONYMOUS_ADDRESS,
				UpdateDonorValidator::SOURCE_ADDRESS_TYPE
			),
			$result->getFirstViolation()
		);
	}

	public function testGivenFailingDonorValidator_validationFails(): void {
		$addressViolation = new ValidationResult( new ConstraintViolation( '', 'donor_name_missing', 'first_name' ) );
		$donorValidator = $this->createPartialMock(
			AddressValidator::class,
			[ 'validatePostalAddress', 'validatePersonName' ]
		);
		$donorValidator->method( 'validatePersonName' )->willReturn( $addressViolation );
		$validator = new UpdateDonorValidator( $donorValidator, new SucceedingEmailValidator() );

		$result = $validator->validateDonorData( $this->newEmptyUpdateDonorRequest() );

		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals(
			$addressViolation->getViolations()[0],
			$result->getFirstViolation()
		);
	}

	public function testGivenEmptyDonorRequestValues_validationFails(): void {
		$validator = new UpdateDonorValidator(
			new AddressValidator( ValidatorPatterns::COUNTRY_POSTCODE, ValidatorPatterns::ADDRESS_PATTERNS ),
			new EmailValidator( new SucceedingDomainNameValidator() )
		);
		$result = $validator->validateDonorData( $this->newEmptyUpdateDonorRequest() );
		$violations = $result->getViolations();
		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals( 'salutation', $violations[0]->getSource() );
		$this->assertEquals( 'firstName', $violations[1]->getSource() );
		$this->assertEquals( 'lastName', $violations[2]->getSource() );
		$this->assertEquals( 'street', $violations[3]->getSource() );
		$this->assertEquals( 'postcode', $violations[4]->getSource() );
		$this->assertEquals( 'city', $violations[5]->getSource() );
		$this->assertEquals( 'country', $violations[6]->getSource() );
		$this->assertEquals( 'email_address_wrong_format', $violations[7]->getMessageIdentifier() );
	}

	public function testGivenInvalidCompanyDonor_validationFails(): void {
		$validator = new UpdateDonorValidator(
			new AddressValidator( ValidatorPatterns::COUNTRY_POSTCODE, ValidatorPatterns::ADDRESS_PATTERNS ),
			new EmailValidator( new SucceedingDomainNameValidator() )
		);
		$result = $validator->validateDonorData( $this->newInvalidUpdateCompanyDonorRequest() );
		$violations = $result->getViolations();
		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals( 'companyName', $violations[0]->getSource() );
		$this->assertEquals( 'street', $violations[1]->getSource() );
		$this->assertEquals( 'postcode', $violations[2]->getSource() );
		$this->assertEquals( 'city', $violations[3]->getSource() );
		$this->assertEquals( 'country', $violations[4]->getSource() );
		$this->assertEquals( 'email_address_wrong_format', $violations[5]->getMessageIdentifier() );
	}

	private function newEmptyUpdateDonorRequest(): UpdateDonorRequest {
		return ( new UpdateDonorRequest() )
			->withType( DonorType::PERSON() )
			->withStreetAddress( '' )
			->withPostalCode( '' )
			->withCity( '' )
			->withCountryCode( '' )
			->withSalutation( '' )
			->withTitle( '' )
			->withFirstName( '' )
			->withLastName( '' )
			->withEmailAddress( '' );
	}

	private function newInvalidUpdateCompanyDonorRequest(): UpdateDonorRequest {
		return ( new UpdateDonorRequest() )
			->withType( DonorType::COMPANY() )
			->withCompanyName( str_repeat( 'TEST', 26 ) )
			->withStreetAddress( str_repeat( 'TEST', 26 ) )
			->withPostalCode( str_repeat( '1', 17 ) )
			->withCity( str_repeat( 'TEST', 26 ) )
			->withCountryCode( str_repeat( 'TEST', 26 ) )
			->withEmailAddress( '' );
	}
}
