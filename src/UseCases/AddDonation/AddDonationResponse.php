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

	private ?string $updateToken = null;

	private ?string $accessToken = null;

	private ?string $paymentProviderRedirectUrl = null;

	public static function newSuccessResponse( Donation $donation, string $updateToken, string $accessToken, ?string $paymentProviderRedirectUrl ): self {
		$response = new self();
		$response->donation = $donation;
		$response->updateToken = $updateToken;
		$response->accessToken = $accessToken;
		$response->paymentProviderRedirectUrl = $paymentProviderRedirectUrl;
		return $response;
	}

	/**
	 * @param ConstraintViolation[] $errors
	 *
	 * @return self
	 */
	public static function newFailureResponse( array $errors ): self {
		$response = new self();
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
	 * @return Donation|null
	 */
	public function getDonation(): ?Donation {
		return $this->donation;
	}

	public function getUpdateToken(): ?string {
		return $this->updateToken;
	}

	public function getAccessToken(): ?string {
		return $this->accessToken;
	}

	public function getPaymentProviderRedirectUrl(): ?string {
		return $this->paymentProviderRedirectUrl;
	}

}
