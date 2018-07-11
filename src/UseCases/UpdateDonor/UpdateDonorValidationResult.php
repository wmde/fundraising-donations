<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

/**
 * @license GNU GPL v2+
 */
class UpdateDonorValidationResult {

	private $violations;

	public function __construct( array $violations = [] ) {
		$this->violations = $violations;
	}

	public function getViolations(): array {
		return $this->violations;
	}

	public function isSuccessful(): bool {
		return empty( $this->violations );
	}

	public function getFirstViolation(): string {
		if ( empty( $this->violations ) ) {
			throw new \RuntimeException( 'There are no validation errors.' );
		}
		return reset( $this->violations );
	}

}