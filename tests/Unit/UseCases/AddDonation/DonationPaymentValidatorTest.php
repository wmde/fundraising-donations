<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\DonationPaymentValidator;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\PaymentType;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\DonationPaymentValidator
 */
class DonationPaymentValidatorTest extends TestCase {

	private const ALLOWED_PAYMENT_TYPES = [
		PaymentType::DirectDebit
	];

	/**
	 * @dataProvider getValidAmounts
	 */
	public function testGivenValidAmount_validatorReturnsNoViolations( float $amount ): void {
		$validator = new DonationPaymentValidator( self::ALLOWED_PAYMENT_TYPES );

		$validationResult = $validator->validatePaymentData( Euro::newFromFloat( $amount ), PaymentInterval::OneTime, PaymentType::DirectDebit );

		$this->assertTrue( $validationResult->isSuccessful() );
	}

	public static function getValidAmounts(): iterable {
		yield 'average amount' => [ 25.00 ];
		yield 'lower bound edge case' => [ 1.00 ];
		yield 'upper bound edge case' => [ 99999.99 ];
	}

	public function testGivenSmallAmount_validatorReturnsViolation(): void {
		$validator = new DonationPaymentValidator( self::ALLOWED_PAYMENT_TYPES );

		$validationResult = $validator->validatePaymentData( Euro::newFromCents( 99 ), PaymentInterval::OneTime, PaymentType::DirectDebit );

		$this->assertFalse( $validationResult->isSuccessful() );
		$this->assertEquals(
			new ConstraintViolation( 99, DonationPaymentValidator::AMOUNT_TOO_LOW, DonationPaymentValidator::SOURCE_AMOUNT ),
			$validationResult->getValidationErrors()[0]
		);
	}

	public function testGivenLargeAmount_validatorReturnsViolation(): void {
		$validator = new DonationPaymentValidator( self::ALLOWED_PAYMENT_TYPES );

		$validationResult = $validator->validatePaymentData( Euro::newFromInt( 100_000 ), PaymentInterval::OneTime, PaymentType::DirectDebit );

		$this->assertFalse( $validationResult->isSuccessful() );
		$this->assertEquals(
			new ConstraintViolation( 10_000_000, DonationPaymentValidator::AMOUNT_TOO_HIGH, DonationPaymentValidator::SOURCE_AMOUNT ),
			$validationResult->getValidationErrors()[0]
		);
	}

	public function testGivenDisallowedPaymentType_validatorReturnsViolation(): void {
		$validator = new DonationPaymentValidator( self::ALLOWED_PAYMENT_TYPES );

		$validationResult = $validator->validatePaymentData( Euro::newFromInt( 99 ), PaymentInterval::OneTime, PaymentType::Paypal );

		$this->assertFalse( $validationResult->isSuccessful() );
		$this->assertEquals(
			new ConstraintViolation( PaymentType::Paypal->value, DonationPaymentValidator::FORBIDDEN_PAYMENT_TYPE, DonationPaymentValidator::SOURCE_PAYMENT_TYPE ),
			$validationResult->getValidationErrors()[0]
		);
	}
}
