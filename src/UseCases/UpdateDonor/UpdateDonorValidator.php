<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\Validators\AddressValidator;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @license GPL-2.0-or-later
 */
class UpdateDonorValidator {

	public const VIOLATION_ANONYMOUS_ADDRESS = 'address_form_error';
	public const SOURCE_ADDRESS_TYPE = 'addressType';

	private AddressValidator $addressValidator;
	private EmailValidator $emailValidator;

	public function __construct( AddressValidator $addressValidator, EmailValidator $emailValidator ) {
		$this->addressValidator = $addressValidator;
		$this->emailValidator = $emailValidator;
	}

	public function validateDonorData( UpdateDonorRequest $donorRequest ): UpdateDonorValidationResult {
		$donorType = $donorRequest->getDonorType();
		if ( $donorType->is( DonorType::PERSON() ) ) {
			$nameViolations = $this->getPersonViolations( $donorRequest );
		} elseif ( $donorType->is( DonorType::COMPANY() ) ) {
			$nameViolations = $this->getCompanyViolations( $donorRequest );
		} elseif ( $donorType->is( DonorType::ANONYMOUS() ) ) {
			return new UpdateDonorValidationResult( $this->getAnonymousViolation( $donorRequest ) );
		} else {
			throw new \InvalidArgumentException( sprintf( ' Unknown donor type: %s', $donorType ) );
		}

		$violations = array_merge(
			$nameViolations,
			$this->getAddressViolations( $donorRequest ),
			$this->getEmailViolations( $donorRequest )
		);
		if ( $violations ) {
			return new UpdateDonorValidationResult( ...$violations );
		}

		return new UpdateDonorValidationResult();
	}

	/**
	 * @param UpdateDonorRequest $donorRequest
	 *
	 * @return ConstraintViolation[]
	 */
	private function getPersonViolations( UpdateDonorRequest $donorRequest ): array {
		return $this->addressValidator->validatePersonName(
			$donorRequest->getSalutation(),
			$donorRequest->getTitle(),
			$donorRequest->getFirstName(),
			$donorRequest->getLastName()
		)->getViolations();
	}

	/**
	 * @param UpdateDonorRequest $donorRequest
	 *
	 * @return ConstraintViolation[]
	 */
	private function getCompanyViolations( UpdateDonorRequest $donorRequest ): array {
		return $this->addressValidator->validateCompanyName(
			$donorRequest->getCompanyName()
		)->getViolations();
	}

	private function getAnonymousViolation( UpdateDonorRequest $donorRequest ): ConstraintViolation {
		return new ConstraintViolation(
			$donorRequest->getDonorType(),
			self::VIOLATION_ANONYMOUS_ADDRESS,
			self::SOURCE_ADDRESS_TYPE
		);
	}

	/**
	 * @param UpdateDonorRequest $donorRequest
	 *
	 * @return ConstraintViolation[]
	 */
	private function getAddressViolations( UpdateDonorRequest $donorRequest ): array {
		return $this->addressValidator->validatePostalAddress(
			$donorRequest->getStreetAddress(),
			$donorRequest->getPostalCode(),
			$donorRequest->getCity(),
			$donorRequest->getCountryCode()
		)->getViolations();
	}

	/**
	 * @param UpdateDonorRequest $donorRequest
	 *
	 * @return ConstraintViolation[]
	 */
	private function getEmailViolations( UpdateDonorRequest $donorRequest ): array {
		return $this->emailValidator->validate( $donorRequest->getEmailAddress() )->getViolations();
	}
}
