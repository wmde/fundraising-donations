<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Tests\Data\ValidAddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationPolicyValidator;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AmountPolicyValidator;
use WMDE\FunValidators\Validators\TextPolicyValidator;

/**
 * @covers WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationPolicyValidator
 *
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddDonationPolicyValidatorTest extends \PHPUnit\Framework\TestCase {

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
		$amountPolicyValidator = $this->getMockBuilder( AmountPolicyValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$amountPolicyValidator->method( 'validate' )->willReturn(
			new ValidationResult( new ConstraintViolation( 1000, 'too-high', 'amount' ) )
		);
		return $amountPolicyValidator;
	}

	private function newSucceedingAmountValidator(): AmountPolicyValidator {
		$amountPolicyValidator = $this->getMockBuilder( AmountPolicyValidator::class )
			->disableOriginalConstructor()
			->getMock();

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
	public function testWhenEmailAddressIsNotBlacklisted_isAutoDeletedReturnsFalse( string $emailAddress ): void {
		$policyValidator = $this->newPolicyValidatorWithEmailBlacklist();
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

	/** @dataProvider blacklistedEmailAddressProvider */
	public function testWhenEmailAddressIsBlacklisted_isAutoDeletedReturnsTrue( string $emailAddress ): void {
		$policyValidator = $this->newPolicyValidatorWithEmailBlacklist();
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorEmailAddress( $emailAddress );

		$this->assertTrue( $policyValidator->isAutoDeleted( $request ) );
	}

	public function blacklistedEmailAddressProvider(): array {
		return [
			[ 'blocked.person@bar.baz' ],
			[ 'test@example.com' ],
			[ 'Test@EXAMPLE.com' ]
		];
	}

	private function newPolicyValidatorWithEmailBlacklist(): AddDonationPolicyValidator {
		$policyValidator = new AddDonationPolicyValidator(
			$this->newSucceedingAmountValidator(),
			$this->newSucceedingTextPolicyValidator(),
			[ '/^blocked.person@bar\.baz$/', '/@example.com$/i' ]
		);

		return $policyValidator;
	}

}
