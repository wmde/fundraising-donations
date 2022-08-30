<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\PaymentMigration;

class ConversionResult {

	private const BUFFER_SIZE = 20;

	/**
	 * @var ResultObject[]
	 */
	private array $errors = [];
	/**
	 * @var ResultObject[]
	 */
	private array $warnings = [];
	private int $donationCount = 0;

	public function addError( string $itemType, array $row ): void {
		if ( !isset( $this->errors[$itemType] ) ) {
			$this->errors[$itemType] = new ResultObject( self::BUFFER_SIZE, $row );
		} else {
			$this->errors[$itemType]->add( $row );
		}
	}

	public function addWarning( string $itemType, array $row ): void {
		if ( !isset( $this->warnings[$itemType] ) ) {
			$this->warnings[$itemType] = new ResultObject( self::BUFFER_SIZE, $row );
		} else {
			$this->warnings[$itemType]->add( $row );
		}
	}

	public function addRow(): self {
		$this->donationCount++;
		return $this;
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function getWarnings(): array {
		return $this->warnings;
	}

	public function getDonationCount(): int {
		return $this->donationCount;
	}
}
