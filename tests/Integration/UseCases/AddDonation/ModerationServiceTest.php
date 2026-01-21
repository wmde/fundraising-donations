<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\AddDonation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidAddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentParameters;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AmountPolicyValidator;
use WMDE\FunValidators\Validators\TextPolicyValidator;

#[CoversClass( ModerationService::class )]
class ModerationServiceTest extends TestCase {

	public function testTooHighAmountGiven_needsModerationReturnsTrue(): void {
		$policyValidator = new ModerationService(
			$this->newFailingAmountValidator(),
			$this->newSucceedingTextPolicyValidator()
		);
		$this->assertTrue( $policyValidator->needsModeration( ValidAddDonationRequest::getRequest() ) );
	}

	public function testGivenBadWords_needsModerationReturnsTrue(): void {
		$policyValidator = new ModerationService(
			$this->newSucceedingAmountValidator(),
			$this->newFailingTextPolicyValidator()
		);
		$this->assertTrue( $policyValidator->needsModeration( ValidAddDonationRequest::getRequest() ) );
	}

	public function testGivenBadWordsWithAnonymousRequest_needsModerationReturnsFalse(): void {
		$policyValidator = new ModerationService(
			$this->newSucceedingAmountValidator(),
			$this->newFailingTextPolicyValidator()
		);
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::ANONYMOUS );

		$this->assertFalse( $policyValidator->needsModeration( $request ) );
	}

	#[DataProvider( 'paymentTypeProvider' )]
	public function testGivenExternalPayment_needsModerationReturnsFalse( string $paymentType, bool $expectedNeedsModeration ): void {
		$policyValidator = new ModerationService(
			$this->newFailingAmountValidator(),
			$this->newFailingTextPolicyValidator()
		);
		$request = ValidAddDonationRequest::getRequest();
		$request->setPaymentParameters( new PaymentParameters(
			100,
			0,
			$paymentType
		) );

		$this->assertSame( $expectedNeedsModeration, $policyValidator->needsModeration( $request ) );
	}

	/**
	 * @return iterable<array{string,boolean}>
	 */
	public static function paymentTypeProvider(): iterable {
		yield 'Paypal does not need moderation' => [ 'PPL', false ];
		yield 'Credit Card does not need moderation' => [ 'MCP', false ];
		yield 'Sofort does not need moderation' => [ 'SUB', false ];
		yield 'Direct Debit needs moderation' => [ 'BEZ', true ];
		yield 'Bank Transfer needs moderation' => [ 'UEB', true ];
	}

	private function newFailingAmountValidator(): AmountPolicyValidator {
		return $this->createConfiguredStub(
			AmountPolicyValidator::class,
			[ 'validate' => new ValidationResult( new ConstraintViolation( 1000, 'too-high', 'amount' ) ) ]
		);
	}

	private function newSucceedingAmountValidator(): AmountPolicyValidator {
		return $this->createConfiguredStub(
			AmountPolicyValidator::class,
			[ 'validate' => new ValidationResult() ]
		);
	}

	private function newSucceedingTextPolicyValidator(): TextPolicyValidator {
		return $this->createConfiguredStub(
			TextPolicyValidator::class,
			[ 'textIsHarmless' => true ]
		);
	}

	private function newFailingTextPolicyValidator(): TextPolicyValidator {
		return $this->createConfiguredStub(
			TextPolicyValidator::class,
			[ 'hasHarmlessContent' => false ]
		);
	}

	#[DataProvider( 'allowedEmailAddressProvider' )]
	public function testWhenEmailAddressIsNotOnBlockList_needsModerationReturnsFalse( string $emailAddress ): void {
		$policyValidator = $this->newPolicyValidatorWithForbiddenEmails();
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorEmailAddress( $emailAddress );

		$this->assertFalse( $policyValidator->needsModeration( $request ) );
	}

	/**
	 * @return array<string[]>
	 */
	public static function allowedEmailAddressProvider(): array {
		return [
			[ 'other.person@bar.baz' ],
			[ 'test@example.computer.says.no' ],
			[ 'some.person@gmail.com' ]
		];
	}

	#[DataProvider( 'forbiddenEmailsProvider' )]
	public function testWhenEmailAddressIsOnBlockList_needsModerationReturnsTrue( string $emailAddress ): void {
		$policyValidator = $this->newPolicyValidatorWithForbiddenEmails();
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorEmailAddress( $emailAddress );

		$result = $policyValidator->moderateDonationRequest( $request );

		$this->assertTrue( $result->needsModeration() );
		$this->assertCount( 1, $result->getViolations() );
		$this->assertSame( ModerationIdentifier::EMAIL_BLOCKED, $result->getViolations()[0]->getModerationIdentifier() );
		$this->assertSame( AddDonationValidationResult::SOURCE_DONOR_EMAIL, $result->getViolations()[0]->getSource() );
	}

	/**
	 * @return array<string[]>
	 */
	public static function forbiddenEmailsProvider(): array {
		return [
			[ 'blocked.person@bar.baz' ],
			[ 'foo@example.com' ]
		];
	}

	private function newPolicyValidatorWithForbiddenEmails(): ModerationService {
		return new ModerationService(
			$this->newSucceedingAmountValidator(),
			$this->newSucceedingTextPolicyValidator(),
			[ 'blocked.person@bar.baz', 'foo@example.com' ]
		);
	}

	public function testGivenAnonymousDonorWithEmailData_itDoesNotModerateEmail(): void {
		$policyValidator = $this->newPolicyValidatorWithForbiddenEmails();
		$request = ValidAddDonationRequest::getRequest();
		$request->setDonorType( DonorType::ANONYMOUS );
		$request->setDonorEmailAddress( 'blocked.person@bar.baz' );

		$this->assertFalse( $policyValidator->needsModeration( $request ) );
	}
}
