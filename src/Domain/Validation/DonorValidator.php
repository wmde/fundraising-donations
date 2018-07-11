<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Validation;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\UseCases\ValidateDonor\ValidateDonorResponse as Response;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @license GNU GPL v2+
 */
class DonorValidator {

	private $emailValidator;

	/**
	 * @var DonorDataInterface
	 */
	private $donorData;

	/**
	 * @var ConstraintViolation[]
	 */
	private $violations;

	private $maximumFieldLengths = [
		Response::SOURCE_EMAIL => 250,
		Response::SOURCE_COMPANY => 100,
		Response::SOURCE_FIRST_NAME => 50,
		Response::SOURCE_LAST_NAME => 50,
		Response::SOURCE_SALUTATION => 16,
		Response::SOURCE_TITLE => 16,
		Response::SOURCE_STREET_ADDRESS => 100,
		Response::SOURCE_POSTAL_CODE => 8,
		Response::SOURCE_CITY => 100,
		Response::SOURCE_COUNTRY => 8,
	];

	public function __construct( DonorDataInterface $donorData, EmailValidator $mailValidator ) {
		$this->donorData = $donorData;
		$this->emailValidator = $mailValidator;
		$this->violations = [];
	}

	public function validateDonorData() {
		$this->validateDonorName();
		$this->validateDonorEmail();
		$this->validateDonorAddress();
	}

	private function validateDonorEmail(): void {
		if ( $this->donorIsAnonymous() ) {
			return;
		}

		if ( $this->emailValidator->validate( $this->donorData->getEmailAddress() )->hasViolations() ) {
			$this->addViolations(
				[
					new ConstraintViolation(
						$this->donorData->getEmailAddress(),
						Response::VIOLATION_MISSING,
						Response::SOURCE_EMAIL
					)
				]
			);
		} else {
			$this->validateFieldLength( $this->donorData->getEmailAddress(), Response::SOURCE_EMAIL );
		}
	}

	private function validateDonorName(): void {
		if ( $this->donorIsAnonymous() ) {
			return;
		}

		if ( $this->donorIsCompany() ) {
			$this->validateCompanyName();
		} else {
			$this->validatePersonName();
		}
	}

	private function validateCompanyName(): void {
		if ( $this->donorData->getCompanyName() === '' ) {
			$this->violations[] = new ConstraintViolation(
				$this->donorData->getCompanyName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_COMPANY
			);
		} else {
			$this->validateFieldLength( $this->donorData->getCompanyName(), Response::SOURCE_COMPANY );
		}
	}

	public function validatePersonName(): void {
		$violations = [];

		if ( $this->donorData->getFirstName() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getFirstName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_FIRST_NAME
			);
		} else {
			$this->validateFieldLength( $this->donorData->getFirstName(), Response::SOURCE_FIRST_NAME );
		}

		if ( $this->donorData->getLastName() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getLastName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_LAST_NAME
			);
		} else {
			$this->validateFieldLength( $this->donorData->getLastName(), Response::SOURCE_LAST_NAME );
		}

		if ( $this->donorData->getSalutation() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getSalutation(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_SALUTATION
			);
		} else {
			$this->validateFieldLength( $this->donorData->getSalutation(), Response::SOURCE_SALUTATION );
		}

		$this->validateFieldLength( $this->donorData->getTitle(), Response::SOURCE_TITLE );

		// TODO: check if donor title is in the list of allowed titles?

		$this->addViolations( $violations );
	}

	private function validateDonorAddress(): void {
		if ( $this->donorIsAnonymous() ) {
			return;
		}

		$violations = [];

		if ( $this->donorData->getStreetAddress() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getStreetAddress(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_STREET_ADDRESS
			);
		} else {
			$this->validateFieldLength( $this->donorData->getStreetAddress(), Response::SOURCE_STREET_ADDRESS );
		}

		if ( $this->donorData->getPostalCode() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getPostalCode(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_POSTAL_CODE
			);
		} else {
			$this->validateFieldLength( $this->donorData->getPostalCode(), Response::SOURCE_POSTAL_CODE );
		}

		if ( $this->donorData->getCity() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getCity(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_CITY
			);
		} else {
			$this->validateFieldLength( $this->donorData->getCity(), Response::SOURCE_CITY );
		}

		if ( $this->donorData->getCountryCode() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getCountryCode(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_COUNTRY
			);
		} else {
			$this->validateFieldLength( $this->donorData->getCountryCode(), Response::SOURCE_COUNTRY );
		}

		if ( !preg_match( '/^\\d{4,5}$/', $this->donorData->getPostalCode() ) ) {
			$violations[] = new ConstraintViolation(
				$this->donorData->getPostalCode(),
				Response::VIOLATION_NOT_POSTCODE,
				Response::SOURCE_POSTAL_CODE
			);
		}

		$this->addViolations( $violations );
	}

	private function validateFieldLength( string $value, string $fieldName ): void {
		if ( strlen( $value ) > $this->maximumFieldLengths[$fieldName] ) {
			$this->violations[] = new ConstraintViolation(
				$value,
				Response::VIOLATION_WRONG_LENGTH,
				$fieldName
			);
		}
	}

	public function donorIsAnonymous(): bool {
		return $this->donorData->getDonorType() === DonorName::PERSON_ANONYMOUS;
	}

	public function donorIsCompany(): bool {
		return $this->donorData->getDonorType() === DonorName::PERSON_COMPANY;
	}

	public function donorIsPerson(): bool {
		return $this->donorData->getDonorType() === DonorName::PERSON_PRIVATE;
	}

	private function addViolations( array $violations ): void {
		$this->violations = array_merge( $this->violations, $violations );
	}

	public function getViolations(): array {
		return $this->violations;
	}

}
