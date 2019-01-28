<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\UpdateDonor;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\Validators\AddressValidator;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @license GNU GPL v2+
 */
class UpdateDonorValidator {

	public const VIOLATION_ANONYMOUS_ADDRESS = 'address_form_error';
	public const SOURCE_ADDRESS_TYPE = 'addressType';

	private $addressValidator;
	private $emailValidator;

	public function __construct( AddressValidator $donorValidator, EmailValidator $emailValidator ) {
		$this->addressValidator = $donorValidator;
		$this->emailValidator = $emailValidator;
	}

	public function validateDonorData( UpdateDonorRequest $donorRequest ): UpdateDonorValidationResult {
		if ( $donorRequest->getDonorType() === DonorName::PERSON_PRIVATE ) {
			$nameViolations = $this->getPersonViolations( $donorRequest );
		} elseif ( $donorRequest->getDonorType() === DonorName::PERSON_COMPANY ) {
			$nameViolations = $this->getCompanyViolations( $donorRequest );
		} else {
			return new UpdateDonorValidationResult( $this->getAnonymousViolation( $donorRequest ) );
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

	private function getPersonViolations( UpdateDonorRequest $donorRequest ): array {
		return $this->addressValidator->validatePersonName(
			$donorRequest->getSalutation(),
			$donorRequest->getTitle(),
			$donorRequest->getFirstName(),
			$donorRequest->getLastName()
		)->getViolations();
	}

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

	private function getAddressViolations( UpdateDonorRequest $donorRequest ): array {
		return $this->addressValidator->validatePostalAddress(
			$donorRequest->getStreetAddress(),
			$donorRequest->getPostalCode(),
			$donorRequest->getCity(),
			$donorRequest->getCountryCode()
		)->getViolations();
	}

	private function getEmailViolations( UpdateDonorRequest $donorRequest ): array {
		return $this->emailValidator->validate( $donorRequest->getEmailAddress() )->getViolations();
	}
}
