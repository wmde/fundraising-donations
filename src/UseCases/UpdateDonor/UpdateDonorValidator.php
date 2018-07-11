<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Validation\DonorValidator;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @license GNU GPL v2+
 */
class UpdateDonorValidator {

	private $emailValidator;

	public function __construct( EmailValidator $mailValidator ) {
		$this->emailValidator = $mailValidator;
	}

	public function validateDonorData( UpdateDonorRequest $donorRequest ): UpdateDonorValidationResult {
		$addressValidator = new DonorValidator( $donorRequest, $this->emailValidator );
		if ( $addressValidator->donorIsAnonymous() ) {
			return new UpdateDonorValidationResult( [ UpdateDonorResponse::VIOLATION_GENERIC ] );
		}
		$addressValidator->validateDonorData();
		if ( $addressValidator->getViolations() ) {
			return new UpdateDonorValidationResult( $addressValidator->getViolations() );
		}
		return new UpdateDonorValidationResult();
	}
}
