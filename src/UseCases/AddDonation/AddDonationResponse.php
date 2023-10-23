<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\FunValidators\ConstraintViolation;

class AddDonationResponse {

	/**
	 * @var ConstraintViolation[]
	 */
	private array $validationErrors = [];

	private ?Donation $donation = null;

	private ?string $paymentCompletionUrl = null;

	public static function newSuccessResponse( Donation $donation, string $paymentCompletionUrl ): self {
		$response = new self();
		$response->donation = $donation;
		$response->paymentCompletionUrl = $paymentCompletionUrl;
		return $response;
	}

	/**
	 * @param ConstraintViolation[] $errors
	 *
	 * @return self
	 */
	public static function newFailureResponse( array $errors ): self {
		$response = new self();

		// We need this check because isSuccessful checks for the existence of errors
		if ( count( $errors ) === 0 ) {
			throw new \InvalidArgumentException( 'Failure response must contain at least one error' );
		}

		$response->validationErrors = $errors;
		return $response;
	}

	private function __construct() {
	}

	/**
	 * @return ConstraintViolation[]
	 */
	public function getValidationErrors(): array {
		return $this->validationErrors;
	}

	public function isSuccessful(): bool {
		return empty( $this->validationErrors );
	}

	/**
	 * ATTENTION: We're returning the domain object in order to avoid a verbose read-only response model.
	 * Keep in mind that your presenters should only query the domain object
	 * and NOT call any of its state-changing methods!
	 * @return Donation
	 */
	public function getDonation(): Donation {
		if ( $this->donation === null ) {
			throw new \DomainException( 'Donation is not set. You probably tried to get donation from an Error response' );
		}
		return $this->donation;
	}

	public function getPaymentCompletionUrl(): string {
		if ( $this->paymentCompletionUrl === null ) {
			throw new \DomainException( 'Payment completion URL is not set. You probably tried to get it from an Error response' );
		}
		return $this->paymentCompletionUrl;
	}

}
