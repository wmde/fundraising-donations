<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\AddDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationResponse;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationResponse
 */
class AddDonationResponseTest extends TestCase {
	private const PAYMENT_COMPLETION_URL = 'https://spenden.wikimedia.de/payments/complete';

	public function testGivenSuccessResponse_isSuccessfulReturnsTrue(): void {
		$response = AddDonationResponse::newSuccessResponse(
			ValidDonation::newBankTransferDonation(), self::PAYMENT_COMPLETION_URL
		);

		$this->assertTrue( $response->isSuccessful() );
	}

	public function testGivenFailureResponse_isSuccessfulReturnsFalse(): void {
		$response = AddDonationResponse::newFailureResponse( [ $this->givenConstraintViolation() ] );

		$this->assertFalse( $response->isSuccessful() );
	}

	public function testFailureResponseMustContainAtLeastOneError(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Failure response must contain at least one error' );

		AddDonationResponse::newFailureResponse( [] );
	}

	public function testGivenFailureResponse_getDonationWillThrowException(): void {
		$response = AddDonationResponse::newFailureResponse( [ $this->givenConstraintViolation() ] );

		$this->expectException( \DomainException::class );

		$response->getDonation();
	}

	public function testGivenFailureResponse_getPaymentCompletionUrlWillThrowException(): void {
		$response = AddDonationResponse::newFailureResponse( [ $this->givenConstraintViolation() ] );

		$this->expectException( \DomainException::class );

		$response->getPaymentCompletionUrl();
	}

	private function givenConstraintViolation(): ConstraintViolation {
		return new ConstraintViolation( 'test', 'a sample violation', 'someField' );
	}
}
