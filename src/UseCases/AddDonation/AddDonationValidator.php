<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\Frontend\DonationContext\UseCases\AddDonation\AddDonationValidationResult as Result;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ValidateDonor\ValidateDonorRequest;
use WMDE\Fundraising\Frontend\DonationContext\UseCases\ValidateDonor\ValidateDonorUseCase;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\BankDataValidator;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\Model\PaymentMethods;
use WMDE\Fundraising\Frontend\PaymentContext\Domain\PaymentDataValidator;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddDonationValidator {

	private $paymentDataValidator;
	private $bankDataValidator;
	private $donorValidator;

	/**
	 * @var AddDonationRequest
	 */
	private $request;

	/**
	 * @var ConstraintViolation[]
	 */
	private $violations;

	private $maximumFieldLengths = [
		Result::SOURCE_BANK_NAME => 100,
		Result::SOURCE_BIC => 32,
		Result::SOURCE_TRACKING_SOURCE => 250
	];

	public function __construct( PaymentDataValidator $paymentDataValidator, BankDataValidator $bankDataValidator,
		EmailValidator $emailValidator ) {

		$this->paymentDataValidator = $paymentDataValidator;
		$this->bankDataValidator = $bankDataValidator;
		$this->donorValidator = new ValidateDonorUseCase( $emailValidator );
	}

	public function validate( AddDonationRequest $addDonationRequest ): Result {
		$this->request = $addDonationRequest;
		$this->violations = [];

		$this->validateAmount();
		$this->validatePayment();
		$this->validateBankData();
		$this->validateDonor();
		$this->validateTrackingData();

		return new Result( ...$this->violations );
	}

	private function validateAmount(): void {
		// TODO validate without euro class, put conversion in PaymentDataValidator
		$result = $this->paymentDataValidator->validate(
			$this->request->getAmount()->getEuroFloat(),
			$this->request->getPaymentType()
		);

		$violations = array_map(
			function( ConstraintViolation $violation ) {
				$violation->setSource( Result::SOURCE_PAYMENT_AMOUNT );
				return $violation;
			},
			$result->getViolations()
		);
		$this->addViolations( $violations );
	}

	private function addViolations( array $violations ): void {
		$this->violations = array_merge( $this->violations, $violations );
	}

	private function validateBankData(): void {
		if ( $this->request->getPaymentType() !== PaymentMethod::DIRECT_DEBIT ) {
			return;
		}

		$bankData = $this->request->getBankData();
		$validationResult = $this->bankDataValidator->validate( $bankData );

		$this->addViolations( $validationResult->getViolations() );
		$this->validateFieldLength( $bankData->getBankName(), Result::SOURCE_BANK_NAME );
		$this->validateFieldLength( $bankData->getBic(), Result::SOURCE_BIC );
	}

	private function validatePayment(): void {
		if ( !in_array( $this->request->getPaymentType(), PaymentMethods::getList() ) ) {
			$this->violations[] = new ConstraintViolation(
				$this->request->getPaymentType(),
				Result::VIOLATION_WRONG_PAYMENT_TYPE,
				Result::SOURCE_PAYMENT_TYPE
			);
		}
	}

	private function validateFieldLength( string $value, string $fieldName ): void {
		if ( strlen( $value ) > $this->maximumFieldLengths[$fieldName] ) {
			$this->violations[] = new ConstraintViolation(
				$value,
				Result::VIOLATION_WRONG_LENGTH,
				$fieldName
			);
		}
	}

	private function validateDonor(): void {
		$validateDonorRequest =
			ValidateDonorRequest::newInstance()
				->withCity( $this->request->getDonorCity() )
				->withCompanyName( $this->request->getDonorCompany() )
				->withCountryCode( $this->request->getDonorCountryCode() )
				->withEmailAddress( $this->request->getDonorEmailAddress() )
				->withFirstName( $this->request->getDonorFirstName() )
				->withLastName( $this->request->getDonorLastName() )
				->withPostalCode( $this->request->getDonorPostalCode() )
				->withSalutation( $this->request->getDonorSalutation() )
				->withStreetAddress( $this->request->getDonorStreetAddress() )
				->withTitle( $this->request->getDonorTitle() )
				->withType( $this->request->getDonorType() );

		$this->violations = array_merge(
			$this->violations,
			$this->donorValidator->validateDonor( $validateDonorRequest )->getViolations()
		);
	}

	private function validateTrackingData(): void {
		$this->validateFieldLength( $this->request->getSource(), Result::SOURCE_TRACKING_SOURCE );
		// validation of impression counts is not needed because input is converted to int
		// validation of skin, color and layout is not needed because they are static legacy values and empty.
	}

}