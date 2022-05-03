<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\DonationPaymentValidator;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\DonationPaymentValidator
 */
class DonationPaymentValidatorTest extends TestCase {
	/**
	 * @dataProvider getValidAmounts
	 */
	public function testGivenValidAmount_validatorReturnsNoViolations( float $amount ): void {
		$validator = new DonationPaymentValidator( 1, 100_000 );

		$validationResult = $validator->validatePaymentData( Euro::newFromFloat( $amount ), PaymentInterval::OneTime );

		$this->assertTrue( $validationResult->isSuccessful() );
	}

	public function getValidAmounts(): iterable {
		yield 'average amount' => [ 25.00 ];
		yield 'lower bound edge case' => [ 1.00 ];
		yield 'upper bound edge case' => [ 99999.99 ];
	}

	public function testGivenSmallAmount_validatorReturnsViolation(): void {
		$validator = new DonationPaymentValidator( 1, 100_000 );

		$validationResult = $validator->validatePaymentData( Euro::newFromCents( 99 ), PaymentInterval::OneTime );

		$this->assertFalse( $validationResult->isSuccessful() );
		$this->assertEquals(
			new ConstraintViolation( 99, DonationPaymentValidator::AMOUNT_TOO_LOW, DonationPaymentValidator::SOURCE_AMOUNT ),
			$validationResult->getValidationErrors()[0]
		);
	}

	public function testGivenLargeAmount_validatorReturnsViolation(): void {
		$validator = new DonationPaymentValidator( 1, 100_000 );

		$validationResult = $validator->validatePaymentData( Euro::newFromInt( 100_000 ), PaymentInterval::OneTime );

		$this->assertFalse( $validationResult->isSuccessful() );
		$this->assertEquals(
			new ConstraintViolation( 10_000_000, DonationPaymentValidator::AMOUNT_TOO_HIGH, DonationPaymentValidator::SOURCE_AMOUNT ),
			$validationResult->getValidationErrors()[0]
		);
	}
}