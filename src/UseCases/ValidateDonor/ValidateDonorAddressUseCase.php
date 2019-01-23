<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ValidateDonor;

use WMDE\Fundraising\DonationContext\Domain\Validation\DonorAddressValidator;

/**
 * @license GNU GPL v2+
 */
class ValidateDonorAddressUseCase {

	public function validateDonor( ValidateDonorAddressRequest $donorRequest ): ValidateDonorAddressResponse {
		$addressValidator = new DonorAddressValidator();
		if ( $addressValidator->donorIsAnonymous( $donorRequest ) ) {
			return new ValidateDonorAddressResponse();
		}
		$addressValidator->validate( $donorRequest );
		return new ValidateDonorAddressResponse( ...$addressValidator->getViolations() );
	}
}
