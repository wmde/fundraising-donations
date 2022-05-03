<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidAddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationPolicyValidator;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AmountPolicyValidator;
use WMDE\FunValidators\Validators\TextPolicyValidator;

/**
 * @covers WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationPolicyValidator
 */
class AddDonationPolicyValidatorTest extends TestCase {

	public function testTooHighAmountGiven_needsModerationReturnsTrue(): void {
		$policyValidator = new AddDonationPolicyValidator(
			$this->newFailingAmountValidator(),
			$this->newSucceedingTextPolicyValidator()
		);
		$this->assertTrue( $policyValidator->needsModeration( ValidAddDonationRequest::getRequest() ) );
	}

	public function testGivenBadWords_needsModerationReturnsTrue(): void {
		$policyValidator = new AddDonationPolicyValidator(
			$this->newSucceedingAmountValidator(),
			$this->newFailingTextPolicyValidator()
		);
		$this->assertTrue( $policyValidator->needsModeration( ValidAddDonationRequest::getRequest() ) );
	}

	private function newFailingAmountValidator(): AmountPolicyValidator {
		$amountPolicyValidator = $this->createMock( AmountPolicyValidator::class );
		$amountPolicyValidator->method( 'validate' )->willReturn(
			new ValidationResult( new ConstraintViolation( 1000, 'too-high', 'amount' ) )
		);
		return $amountPolicyValidator;
	}

	private function newSucceedingAmountValidator(): AmountPolicyValidator {
		$amountPolicyValidator = $this->createMock( AmountPolicyValidator::class );
		$amountPolicyValidator->method( 'validate' )->willReturn( new ValidationResult() );
		return $amountPolicyValidator;
	}

	private function newSucceedingTextPolicyValidator(): TextPolicyValidator {
		$succeedingTextPolicyValidator = $this->createMock( TextPolicyValidator::class );
		$succeedingTextPolicyValidator->method( 'textIsHarmless' )->willReturn( true );
		return $succeedingTextPolicyValidator;
	}

	private function newFailingTextPolicyValidator(): TextPolicyValidator {
		$failingTextPolicyValidator = $this->createMock( TextPolicyValidator::class );
		$failingTextPolicyValidator->method( 'hasHarmlessContent' )
			->willReturn( false );
		return $failingTextPolicyValidator;
	}

	/** @dataProvider allowedEmailAddressProvider */
	public function testWhenEmailAddressIsNotForbidden_isAutoDeletedReturnsFalse( string $emailAddress ): void {
		$policyValidator = $this->newPolicyValidatorWithForbiddenEmails();
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorEmailAddress( $emailAddress );

		$this->assertFalse( $policyValidator->isAutoDeleted( ValidAddDonationRequest::getRequest() ) );
	}

	public function allowedEmailAddressProvider(): array {
		return [
			[ 'other.person@bar.baz' ],
			[ 'test@example.computer.says.no' ],
			[ 'some.person@gmail.com' ]
		];
	}

	/** @dataProvider forbiddenEmailsProvider */
	public function testWhenEmailAddressIsForbidden_isAutoDeletedReturnsTrue( string $emailAddress ): void {
		$policyValidator = $this->newPolicyValidatorWithForbiddenEmails();
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorEmailAddress( $emailAddress );

		$this->assertTrue( $policyValidator->isAutoDeleted( $request ) );
	}

	public function forbiddenEmailsProvider(): array {
		return [
			[ 'blocked.person@bar.baz' ],
			[ 'test@example.com' ],
			[ 'Test@EXAMPLE.com' ]
		];
	}

	private function newPolicyValidatorWithForbiddenEmails(): AddDonationPolicyValidator {
		return new AddDonationPolicyValidator(
			$this->newSucceedingAmountValidator(),
			$this->newSucceedingTextPolicyValidator(),
			[ '/^blocked.person@bar\.baz$/', '/@example.com$/i' ]
		);
	}
}
