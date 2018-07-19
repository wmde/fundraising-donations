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

	public function __construct( EmailValidator $mailValidator ) {
		$this->emailValidator = $mailValidator;
		$this->violations = [];
	}

	public function validate( DonorDataInterface $donorData ) {
		$this->donorData = $donorData;
		$this->validateDonorName( $donorData );
		$this->validateDonorEmail( $donorData );
		$this->validateDonorAddress( $donorData );
	}

	private function validateDonorEmail( DonorDataInterface $donorData ): void {
		if ( $this->donorIsAnonymous( $donorData ) ) {
			return;
		}

		if ( $this->emailValidator->validate( $donorData->getEmailAddress() )->hasViolations() ) {
			$this->addViolations(
				[
					new ConstraintViolation(
						$donorData->getEmailAddress(),
						Response::VIOLATION_MISSING,
						Response::SOURCE_EMAIL
					)
				]
			);
		} else {
			$this->validateFieldLength( $donorData->getEmailAddress(), Response::SOURCE_EMAIL );
		}
	}

	private function validateDonorName( DonorDataInterface $donorData ): void {
		if ( $this->donorIsAnonymous( $donorData ) ) {
			return;
		}

		if ( $this->donorIsCompany( $donorData ) ) {
			$this->validateCompanyName( $donorData );
		} else {
			$this->validatePersonName( $donorData );
		}
	}

	private function validateCompanyName( DonorDataInterface $donorData ): void {
		if ( $donorData->getCompanyName() === '' ) {
			$this->violations[] = new ConstraintViolation(
				$donorData->getCompanyName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_COMPANY
			);
		} else {
			$this->validateFieldLength( $donorData->getCompanyName(), Response::SOURCE_COMPANY );
		}
	}

	public function validatePersonName( DonorDataInterface $donorData ): void {
		$violations = [];

		if ( $donorData->getFirstName() === '' ) {
			$violations[] = new ConstraintViolation(
				$donorData->getFirstName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_FIRST_NAME
			);
		} else {
			$this->validateFieldLength( $donorData->getFirstName(), Response::SOURCE_FIRST_NAME );
		}

		if ( $donorData->getLastName() === '' ) {
			$violations[] = new ConstraintViolation(
				$donorData->getLastName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_LAST_NAME
			);
		} else {
			$this->validateFieldLength( $donorData->getLastName(), Response::SOURCE_LAST_NAME );
		}

		if ( $donorData->getSalutation() === '' ) {
			$violations[] = new ConstraintViolation(
				$donorData->getSalutation(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_SALUTATION
			);
		} else {
			$this->validateFieldLength( $donorData->getSalutation(), Response::SOURCE_SALUTATION );
		}

		$this->validateFieldLength( $donorData->getTitle(), Response::SOURCE_TITLE );

		// TODO: check if donor title is in the list of allowed titles?

		$this->addViolations( $violations );
	}

	private function validateDonorAddress( DonorDataInterface $donorData ): void {
		if ( $this->donorIsAnonymous( $donorData ) ) {
			return;
		}

		$violations = [];

		if ( $donorData->getStreetAddress() === '' ) {
			$violations[] = new ConstraintViolation(
				$donorData->getStreetAddress(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_STREET_ADDRESS
			);
		} else {
			$this->validateFieldLength( $donorData->getStreetAddress(), Response::SOURCE_STREET_ADDRESS );
		}

		if ( $donorData->getPostalCode() === '' ) {
			$violations[] = new ConstraintViolation(
				$donorData->getPostalCode(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_POSTAL_CODE
			);
		} else {
			$this->validateFieldLength( $donorData->getPostalCode(), Response::SOURCE_POSTAL_CODE );
		}

		if ( $donorData->getCity() === '' ) {
			$violations[] = new ConstraintViolation(
				$donorData->getCity(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_CITY
			);
		} else {
			$this->validateFieldLength( $donorData->getCity(), Response::SOURCE_CITY );
		}

		if ( $donorData->getCountryCode() === '' ) {
			$violations[] = new ConstraintViolation(
				$donorData->getCountryCode(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_COUNTRY
			);
		} else {
			$this->validateFieldLength( $donorData->getCountryCode(), Response::SOURCE_COUNTRY );
		}

		if ( !preg_match( '/^\\d{4,5}$/', $donorData->getPostalCode() ) ) {
			$violations[] = new ConstraintViolation(
				$donorData->getPostalCode(),
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

	public function donorIsAnonymous( DonorDataInterface $donorData ): bool {
		return $donorData->getDonorType() === DonorName::PERSON_ANONYMOUS;
	}

	public function donorIsCompany( DonorDataInterface $donorData ): bool {
		return $donorData->getDonorType() === DonorName::PERSON_COMPANY;
	}

	public function donorIsPerson( DonorDataInterface $donorData ): bool {
		return $donorData->getDonorType() === DonorName::PERSON_PRIVATE;
	}

	private function addViolations( array $violations ): void {
		$this->violations = array_merge( $this->violations, $violations );
	}

	public function getViolations(): array {
		return $this->violations;
	}

}
