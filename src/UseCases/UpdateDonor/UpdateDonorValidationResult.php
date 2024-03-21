<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;

class UpdateDonorValidationResult extends ValidationResult {

	public function getFirstViolation(): ConstraintViolation {
		if ( empty( $this->getViolations() ) ) {
			throw new \RuntimeException( 'There are no validation errors.' );
		}
		return $this->getViolations()[0];
	}

}
