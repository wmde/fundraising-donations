<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Validation\DonorAddressValidator;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @license GNU GPL v2+
 */
class UpdateDonorValidator {

	public const VIOLATION_ANONYMOUS_ADDRESS = 'donor_change_failure_anonymous_address';
	public const SOURCE_ADDRESS_TYPE = 'addressType';

	private $donorValidator;

	public function __construct( DonorAddressValidator $donorValidator ) {
		$this->donorValidator = $donorValidator;
	}

	public function validateDonorData( UpdateDonorRequest $donorRequest ): UpdateDonorValidationResult {
		if ( $this->donorValidator->donorIsAnonymous( $donorRequest ) ) {
			return new UpdateDonorValidationResult(
				new ConstraintViolation(
					$donorRequest->getDonorType(),
					self::VIOLATION_ANONYMOUS_ADDRESS,
					self::SOURCE_ADDRESS_TYPE
				)
			);
		}
		$this->donorValidator->validate( $donorRequest );
		if ( $this->donorValidator->getViolations() ) {
			return new UpdateDonorValidationResult( ...$this->donorValidator->getViolations() );
		}
		return new UpdateDonorValidationResult();
	}
}
