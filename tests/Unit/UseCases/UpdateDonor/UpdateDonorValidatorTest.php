<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\Domain\Validation\DonorValidator;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorRequest;
use WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidator;
use PHPUnit\Framework\TestCase;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\UpdateDonor\UpdateDonorValidator
 */
class UpdateDonorValidatorTest extends TestCase {

	public function testGivenAnonymousDonor_validationFails() {
		$donorValidator = $this->createPartialMock( DonorValidator::class, ['getViolations'] );
		$donorValidator->method( 'getViolations' )->willReturn( [] );
		$validator = new UpdateDonorValidator( $donorValidator );
		$request = ( new UpdateDonorRequest() )->withType( DonorName::PERSON_ANONYMOUS );

		$result = $validator->validateDonorData( $request );

		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals(
			new ConstraintViolation(
				DonorName::PERSON_ANONYMOUS,
				UpdateDonorValidator::VIOLATION_ANONYMOUS_ADDRESS,
				UpdateDonorValidator::SOURCE_ADDRESS_TYPE
			),
			$result->getFirstViolation()
		);
	}

	public function testGivenFailingDonorValidator_validationFails() {
		$addressViolation = new ConstraintViolation( '', 'donor_name_missing', 'first_name' );
		$donorValidator = $this->createPartialMock( DonorValidator::class, ['getViolations', 'validate' ] );
		$donorValidator->method( 'getViolations' )->willReturn( [ $addressViolation ] );
		$validator = new UpdateDonorValidator( $donorValidator );
		$request = ( new UpdateDonorRequest() )->withType( DonorName::PERSON_PRIVATE );

		$result = $validator->validateDonorData( $request );

		$this->assertFalse( $result->isSuccessful() );
		$this->assertEquals(
			$addressViolation,
			$result->getFirstViolation()
		);
	}

}
