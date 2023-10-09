<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidAddDonationRequest;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidatorPatterns;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\PaymentParameterBuilder;
use WMDE\Fundraising\PaymentContext\Domain\PaymentType;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\SucceedingDomainNameValidator;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AddressValidator;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator
 */
class AddDonationValidatorTest extends TestCase {

	private AddDonationValidator $donationValidator;

	public function setUp(): void {
		$this->donationValidator = $this->newDonationValidator();
	}

	public function testGivenValidDonation_validationIsSuccessful(): void {
		$request = ValidAddDonationRequest::getRequest();
		$this->assertCount( 0, $this->donationValidator->validate( $request )->getViolations() );
	}

	public function testGivenAnonymousDonorAndEmptyAddressFields_validatorReturnsNoViolations(): void {
		$request = ValidAddDonationRequest::getRequest();

		$request->setDonorType( DonorType::ANONYMOUS() );
		$request->setDonorSalutation( '' );
		$request->setDonorTitle( '' );
		$request->setDonorCompany( '' );
		$request->setDonorFirstName( '' );
		$request->setDonorLastName( '' );
		$request->setDonorStreetAddress( '' );
		$request->setDonorPostalCode( '' );
		$request->setDonorCity( '' );
		$request->setDonorCountryCode( '' );
		$request->setDonorEmailAddress( '' );
		$paymentRequest = $request->getPaymentParameters();
		$request->setPaymentParameters( PaymentParameterBuilder::fromExistingParameters( $paymentRequest )
			->withPaymentType( PaymentType::BankTransfer->value )
			->build()
		);

		$this->assertCount( 0, $this->donationValidator->validate( $request )->getViolations() );
	}

	public function testPersonalInfoValidationFails_validatorReturnsFalse(): void {
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::COMPANY() );
		$request->setDonorCompany( '' );

		$this->assertFalse( $this->donationValidator->validate( $request )->isSuccessful() );

		$this->assertConstraintWasViolated(
			$this->donationValidator->validate( $request ),
			AddDonationValidationResult::SOURCE_DONOR_COMPANY
		);
	}

	public function testDonorWithLongFields_validationFails(): void {
		$longText = str_repeat( 'Cats ', 500 );
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorFirstName( $longText );
		$request->setDonorLastName( $longText );
		$request->setDonorTitle( $longText );
		$request->setDonorSalutation( $longText );
		$request->setDonorStreetAddress( $longText );
		$request->setDonorPostalCode( $longText );
		$request->setDonorCity( $longText );
		$request->setDonorCountryCode( $longText );
		$request->setDonorEmailAddress( str_repeat( 'Cats', 500 ) . '@example.com' );

		$result = $this->donationValidator->validate( $request );
		$this->assertFalse( $result->isSuccessful() );

		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_FIRST_NAME );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_LAST_NAME );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_SALUTATION );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_TITLE );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_STREET_ADDRESS );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_CITY );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_POSTAL_CODE );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_COUNTRY );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_EMAIL );
	}

	public function testGivenEmailOnlyDonorWithMissingNameParts_validationFails(): void {
		$request = ValidAddDonationRequest::getRequest();

		$request->setDonorType( DonorType::EMAIL() );
		$request->setDonorSalutation( '' );
		$request->setDonorFirstName( '' );
		$request->setDonorLastName( '' );

		$result = $this->donationValidator->validate( $request );
		$this->assertFalse( $result->isSuccessful() );

		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_FIRST_NAME );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_LAST_NAME );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_SALUTATION );
	}

	public function testGivenCompleteEmailOnlyDonor_validationSucceeds(): void {
		$request = ValidAddDonationRequest::getRequest();

		$request->setDonorType( DonorType::EMAIL() );
		$request->setDonorStreetAddress( '' );
		$request->setDonorPostalCode( '' );
		$request->setDonorCity( '' );
		$request->setDonorCountryCode( '' );

		$this->assertTrue( $this->donationValidator->validate( $request )->isSuccessful() );
	}

	public function testGivenAnonymousDonorWithDirectDebit_validationFails(): void {
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::ANONYMOUS() );

		$result = $this->donationValidator->validate( $request );
		$this->assertFalse( $result->isSuccessful() );

		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_DONOR_ADDRESS_TYPE );
		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_PAYMENT_TYPE );
	}

	public function testGivenEmailOnlyDonorWithDirectDebit_validationSucceeds(): void {
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::EMAIL() );

		$result = $this->donationValidator->validate( $request );

		$this->assertTrue( $result->isSuccessful() );
	}

	private function newDonationValidator(): AddDonationValidator {
		return new AddDonationValidator(
			$this->newEmailValidator(),
			$this->newAddressValidator()
		);
	}

	private function newEmailValidator(): EmailValidator {
		return new EmailValidator( new SucceedingDomainNameValidator() );
	}

	private function assertConstraintWasViolated( ValidationResult $result, string $fieldName ): void {
		$this->assertContainsOnlyInstancesOf( ConstraintViolation::class, $result->getViolations() );

		foreach ( $result->getViolations() as $violation ) {
			if ( $violation->getSource() === $fieldName ) {
				$this->assertTrue( true );
				return;
			}
		}

		$this->fail( 'Failed asserting that constraint for field "' . $fieldName . '"" was violated.' );
	}

	private function newAddressValidator(): AddressValidator {
		return new AddressValidator( ValidatorPatterns::COUNTRY_POSTCODE, ValidatorPatterns::ADDRESS_PATTERNS );
	}
}
