<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Validation;

use WMDE\Fundraising\Frontend\DonationContext\Domain\Model\DonorAddress;
use WMDE\FunValidators\CanValidateField;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\RequiredFieldValidator;

/**
 * @license GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class DonorAddressValidator {
	use CanValidateField;

	public function validate( DonorAddress $address ): ValidationResult {
		$validator = new RequiredFieldValidator();

		return new ValidationResult(
			...array_filter(
				[
					$this->getFieldViolation( $validator->validate( $address->getStreetAddress() ), 'street' ),
					$this->getFieldViolation( $validator->validate( $address->getPostalCode() ), 'postcode' ),
					$this->getFieldViolation( $validator->validate( $address->getCity() ), 'city' ),
					$this->getFieldViolation( $validator->validate( $address->getCountryCode() ), 'country' )
				]
			)
		);
	}

}
