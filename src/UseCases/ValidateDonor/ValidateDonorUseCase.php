<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ValidateDonor;

use WMDE\Fundraising\DonationContext\UseCases\ValidateDonor\ValidateDonorResponse as Response;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ValidateDonorUseCase {

	private $emailValidator;

	/**
	 * @var ValidateDonorRequest
	 */
	private $request;

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
	}

	public function validateDonor( ValidateDonorRequest $donorRequest ): Response {
		$this->request = $donorRequest;
		$this->violations = [];

		$this->validateDonorName();
		$this->validateDonorEmail();
		$this->validateDonorAddress();

		return new Response( ...$this->violations );
	}

	private function validateDonorEmail(): void {
		if ( $this->donorIsAnonymous() ) {
			return;
		}

		if ( $this->emailValidator->validate( $this->request->getEmailAddress() )->hasViolations() ) {
			$this->addViolations(
				[
					new ConstraintViolation(
						$this->request->getEmailAddress(),
						Response::VIOLATION_MISSING,
						Response::SOURCE_EMAIL
					)
				]
			);
		} else {
			$this->validateFieldLength( $this->request->getEmailAddress(), Response::SOURCE_EMAIL );
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
		if ( $this->request->getCompanyName() === '' ) {
			$this->violations[] = new ConstraintViolation(
				$this->request->getCompanyName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_COMPANY
			);
		} else {
			$this->validateFieldLength( $this->request->getCompanyName(), Response::SOURCE_COMPANY );
		}
	}

	private function validatePersonName(): void {
		$violations = [];

		if ( $this->request->getFirstName() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->request->getFirstName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_FIRST_NAME
			);
		} else {
			$this->validateFieldLength( $this->request->getFirstName(), Response::SOURCE_FIRST_NAME );
		}

		if ( $this->request->getLastName() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->request->getLastName(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_LAST_NAME
			);
		} else {
			$this->validateFieldLength( $this->request->getLastName(), Response::SOURCE_LAST_NAME );
		}

		if ( $this->request->getSalutation() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->request->getSalutation(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_SALUTATION
			);
		} else {
			$this->validateFieldLength( $this->request->getSalutation(), Response::SOURCE_SALUTATION );
		}

		$this->validateFieldLength( $this->request->getTitle(), Response::SOURCE_TITLE );

		// TODO: check if donor title is in the list of allowed titles?

		$this->addViolations( $violations );
	}

	private function validateDonorAddress(): void {
		if ( $this->donorIsAnonymous() ) {
			return;
		}

		$violations = [];

		if ( $this->request->getStreetAddress() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->request->getStreetAddress(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_STREET_ADDRESS
			);
		} else {
			$this->validateFieldLength( $this->request->getStreetAddress(), Response::SOURCE_STREET_ADDRESS );
		}

		if ( $this->request->getPostalCode() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->request->getPostalCode(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_POSTAL_CODE
			);
		} else {
			$this->validateFieldLength( $this->request->getPostalCode(), Response::SOURCE_POSTAL_CODE );
		}

		if ( $this->request->getCity() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->request->getCity(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_CITY
			);
		} else {
			$this->validateFieldLength( $this->request->getCity(), Response::SOURCE_CITY );
		}

		if ( $this->request->getCountryCode() === '' ) {
			$violations[] = new ConstraintViolation(
				$this->request->getCountryCode(),
				Response::VIOLATION_MISSING,
				Response::SOURCE_COUNTRY
			);
		} else {
			$this->validateFieldLength( $this->request->getCountryCode(), Response::SOURCE_COUNTRY );
		}

		if ( !preg_match( '/^\\d{4,5}$/', $this->request->getPostalCode() ) ) {
			$violations[] = new ConstraintViolation(
				$this->request->getPostalCode(),
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

	private function donorIsAnonymous(): bool {
		return $this->request->getDonorType() === ValidateDonorRequest::PERSON_ANONYMOUS;
	}

	private function donorIsCompany(): bool {
		return $this->request->getDonorType() === ValidateDonorRequest::PERSON_COMPANY;
	}

	private function addViolations( array $violations ): void {
		$this->violations = array_merge( $this->violations, $violations );
	}

}
