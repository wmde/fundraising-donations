<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ValidateDonor;

use WMDE\Fundraising\DonationContext\Domain\Validation\DonorValidator;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @license GNU GPL v2+
 */
class ValidateDonorUseCase {

	private $emailValidator;
	public function __construct( EmailValidator $mailValidator ) {
		$this->emailValidator = $mailValidator;
	}

	public function validateDonor( ValidateDonorRequest $donorRequest ): ValidateDonorResponse {
		$addressValidator = new DonorValidator( $this->emailValidator );
		if ( $addressValidator->donorIsAnonymous( $donorRequest ) ) {
			return new ValidateDonorResponse();
		}
		$addressValidator->validate( $donorRequest );
		return new ValidateDonorResponse( ...$addressValidator->getViolations() );
	}
}
