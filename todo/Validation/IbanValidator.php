<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Validation;

use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\Iban;

/**
 * @licence GNU GPL v2+
 */
interface IbanValidator {

	public function validate( Iban $value, string $fieldName = '' ): ValidationResult;

	public function isIbanBlocked( Iban $iban ): bool;

}
