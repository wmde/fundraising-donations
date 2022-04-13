<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidAddDonationRequest;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidatorPatterns;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator;
use WMDE\Fundraising\PaymentContext\Domain\BankDataValidationResult;
use WMDE\Fundraising\PaymentContext\Domain\BankDataValidator;
use WMDE\Fundraising\PaymentContext\Domain\DomainSpecificPaymentValidator;
use WMDE\Fundraising\PaymentContext\Domain\IbanBlockList;
use WMDE\Fundraising\PaymentContext\Domain\IbanValidator;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\PaymentValidator;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\SucceedingDomainNameValidator;
use WMDE\FunValidators\ValidationResponse;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AddressValidator;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidator
 *
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddDonationValidatorTest extends TestCase {

	private AddDonationValidator $donationValidator;

	public function setUp(): void {
		// TODO constructor currently throws an exception, reactivate this when the dependencies have been fixed
		//$this->donationValidator = $this->newDonationValidator();
	}

	public function testGivenValidDonation_validationIsSuccessful(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$request = ValidAddDonationRequest::getRequest();
		$this->assertEmpty( $this->donationValidator->validate( $request )->getViolations() );
	}

	public function testGivenAnonymousDonorAndEmptyAddressFields_validatorReturnsNoViolations(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
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

		$this->assertEmpty( $this->donationValidator->validate( $request )->getViolations() );
	}

	public function testGivenNoPaymentType_validatorReturnsFalse(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$request = ValidAddDonationRequest::getRequest();
		$request->setPaymentType( '' );

		$this->assertFalse( $this->donationValidator->validate( $request )->isSuccessful() );

		$this->assertConstraintWasViolated(
			$this->donationValidator->validate( $request ),
			AddDonationValidationResult::SOURCE_PAYMENT_TYPE
		);
	}

	public function testGivenUnsupportedPaymentType_validatorReturnsFalse(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$request = ValidAddDonationRequest::getRequest();
		$request->setPaymentType( 'KaiCoin' );

		$this->assertFalse( $this->donationValidator->validate( $request )->isSuccessful() );

		$this->assertConstraintWasViolated(
			$this->donationValidator->validate( $request ),
			AddDonationValidationResult::SOURCE_PAYMENT_TYPE
		);
	}

	public function testPersonalInfoValidationFails_validatorReturnsFalse(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::COMPANY() );
		$request->setDonorCompany( '' );

		$this->assertFalse( $this->donationValidator->validate( $request )->isSuccessful() );

		$this->assertConstraintWasViolated(
			$this->donationValidator->validate( $request ),
			AddDonationValidationResult::SOURCE_DONOR_COMPANY
		);
	}

	public function testGivenFailingBankDataValidator_validatorReturnsFalse(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$bankDataValidator = $this->createMock( BankDataValidator::class );
		$bankDataValidator->method( 'validate' )->willReturn( new ValidationResult(
			new ConstraintViolation(
				'',
				BankDataValidationResult::VIOLATION_MISSING,
				BankDataValidationResult::SOURCE_IBAN
			)
		) );
		$validator = new AddDonationValidator(
			new PaymentValidator( $this->newPassingDomainSpecificValidator() ),
			$bankDataValidator,
			$this->newEmptyIbanBlockList(),
			$this->newEmailValidator(),
			$this->newAddressValidator()
		);
		$request = ValidAddDonationRequest::getRequest();

		$result = $validator->validate( $request );
		$this->assertFalse( $result->isSuccessful() );

		$this->assertConstraintWasViolated( $result, BankDataValidationResult::SOURCE_IBAN );
	}

	public function testGivenBlockedIban_validatorReturnsFalse(): void {
		$this->markTestIncomplete( 'IBAN blocklist checking should be part of the payment validation, remove this test when that\'s been implemented' );
		$validator = new AddDonationValidator(
			new PaymentValidator( $this->newPassingDomainSpecificValidator() ),
			$this->newBankDataValidator(),
			new IbanBlocklist( [ ValidDonation::PAYMENT_IBAN ] ),
			$this->newEmailValidator(),
			$this->newAddressValidator()
		);
		$request = ValidAddDonationRequest::getRequest();

		$result = $validator->validate( $request );
		$this->assertFalse( $result->isSuccessful() );

		$this->assertConstraintWasViolated( $result, BankDataValidationResult::SOURCE_IBAN );
	}

	public function testAmountTooLow_validatorReturnsFalse(): void {
		$this->markTestIncomplete( 'TODO: Create DomainSpecificPaymentValidator implementation for amount checking' );
		$request = ValidAddDonationRequest::getRequest();
		$request->setAmount( Euro::newFromCents( 50 ) );

		$result = $this->donationValidator->validate( $request );
		$this->assertFalse( $result->isSuccessful() );

		$this->assertConstraintWasViolated( $result, AddDonationValidationResult::SOURCE_PAYMENT_AMOUNT );
	}

	public function testDonorWithLongFields_validationFails(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
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
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
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
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$request = ValidAddDonationRequest::getRequest();

		$request->setDonorType( DonorType::EMAIL() );
		$request->setDonorStreetAddress( '' );
		$request->setDonorPostalCode( '' );
		$request->setDonorCity( '' );
		$request->setDonorCountryCode( '' );

		$this->assertTrue( $this->donationValidator->validate( $request )->isSuccessful() );
	}

	private function newDonationValidator(): AddDonationValidator {
		return new AddDonationValidator(
			new PaymentValidator( $this->newPassingDomainSpecificValidator() ),
			$this->newBankDataValidator(),
			$this->newEmptyIbanBlockList(),
			$this->newEmailValidator(),
			$this->newAddressValidator()
		);
	}

	private function newBankDataValidator(): BankDataValidator {
		$ibanValidatorMock = $this->getMockBuilder( IbanValidator::class )->disableOriginalConstructor()->getMock();
		$ibanValidatorMock->method( 'validate' )
			->willReturn( new ValidationResult() );

		return new BankDataValidator( $ibanValidatorMock );
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

		$this->assertTrue(
			false,
			'Failed asserting that constraint for field "' . $fieldName . '"" was violated.'
		);
	}

	private function newEmptyIbanBlockList(): IbanBlockList {
		return new IbanBlockList( [] );
	}

	private function newAddressValidator(): AddressValidator {
		return new AddressValidator( ValidatorPatterns::COUNTRY_POSTCODE, ValidatorPatterns::ADDRESS_PATTERNS );
	}

	private function newPassingDomainSpecificValidator(): DomainSpecificPaymentValidator {
		return new class implements DomainSpecificPaymentValidator {
			public function validatePaymentData( Euro $amount, PaymentInterval $interval ): ValidationResponse {
				return ValidationResponse::newSuccessResponse();
			}

		};
	}

}
