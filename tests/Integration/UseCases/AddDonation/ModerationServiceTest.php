<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidAddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AmountPolicyValidator;
use WMDE\FunValidators\Validators\TextPolicyValidator;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService
 */
class ModerationServiceTest extends TestCase {

	public function testTooHighAmountGiven_needsModerationReturnsTrue(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$policyValidator = new ModerationService(
			$this->newFailingAmountValidator(),
			$this->newSucceedingTextPolicyValidator()
		);
		$this->assertTrue( $policyValidator->needsModeration( ValidAddDonationRequest::getRequest() ) );
	}

	public function testGivenBadWords_needsModerationReturnsTrue(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$policyValidator = new ModerationService(
			$this->newSucceedingAmountValidator(),
			$this->newFailingTextPolicyValidator()
		);
		$this->assertTrue( $policyValidator->needsModeration( ValidAddDonationRequest::getRequest() ) );
	}

	public function testGivenBadWordsWithAnonymousRequest_needsModerationReturnsFalse(): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$policyValidator = new ModerationService(
			$this->newSucceedingAmountValidator(),
			$this->newFailingTextPolicyValidator()
		);
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::ANONYMOUS() );

		$this->assertFalse( $policyValidator->needsModeration( $request ) );
	}

	/**
	 * @return iterable<array{string,boolean}>
	 */
	public function paymentTypeProvider(): iterable {
		yield 'Paypal does not need moderation' => [ 'PPL', false ];
		yield 'Credit Card does not need moderation' => [ 'MCP', false ];
		yield 'Sofort does not need moderation' => [ 'SUB', false ];
		yield 'Direct Debit needs moderation' => [ 'BEZ', true ];
		yield 'Bank Transfer needs moderation' => [ 'UEB', true ];
	}

	private function newFailingAmountValidator(): AmountPolicyValidator {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$amountPolicyValidator = $this->createMock( AmountPolicyValidator::class );
		$amountPolicyValidator->method( 'validate' )->willReturn(
			new ValidationResult( new ConstraintViolation( 1000, 'too-high', 'amount' ) )
		);
		return $amountPolicyValidator;
	}

	private function newSucceedingAmountValidator(): AmountPolicyValidator {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$amountPolicyValidator = $this->createMock( AmountPolicyValidator::class );
		$amountPolicyValidator->method( 'validate' )->willReturn( new ValidationResult() );
		return $amountPolicyValidator;
	}

	private function newSucceedingTextPolicyValidator(): TextPolicyValidator {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$succeedingTextPolicyValidator = $this->createMock( TextPolicyValidator::class );
		$succeedingTextPolicyValidator->method( 'textIsHarmless' )->willReturn( true );
		return $succeedingTextPolicyValidator;
	}

	private function newFailingTextPolicyValidator(): TextPolicyValidator {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
		$failingTextPolicyValidator = $this->createMock( TextPolicyValidator::class );
		$failingTextPolicyValidator->method( 'hasHarmlessContent' )
			->willReturn( false );
		return $failingTextPolicyValidator;
	}

	/** @dataProvider allowedEmailAddressProvider */
	public function testWhenEmailAddressIsNotForbidden_isAutoDeletedReturnsFalse( string $emailAddress ): void {
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
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
		$this->markTestIncomplete( 'This should work when we changed the amount field in request to int and removed the error' );
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

	private function newPolicyValidatorWithForbiddenEmails(): ModerationService {
		return new ModerationService(
			$this->newSucceedingAmountValidator(),
			$this->newSucceedingTextPolicyValidator(),
			[ '/^blocked.person@bar\.baz$/', '/@example.com$/i' ]
		);
	}

	public function testGivenAnonymousDonorWithEmailData_itIgnoresForbiddenEmails(): void {
		$policyValidator = $this->newPolicyValidatorWithForbiddenEmails();
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::ANONYMOUS() );
		$request->setDonorEmailAddress( 'blocked.person@bar.baz' );

		$this->assertFalse( $policyValidator->isAutoDeleted( $request ) );
	}
}
