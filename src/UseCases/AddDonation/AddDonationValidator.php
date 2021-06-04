<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult as Result;
use WMDE\Fundraising\PaymentContext\Domain\BankDataValidationResult;
use WMDE\Fundraising\PaymentContext\Domain\BankDataValidator;
use WMDE\Fundraising\PaymentContext\Domain\IbanBlocklist;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethods;
use WMDE\Fundraising\PaymentContext\Domain\PaymentDataValidator;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResult;
use WMDE\FunValidators\Validators\AddressValidator;
use WMDE\FunValidators\Validators\EmailValidator;

/**
 * @license GPL-2.0-or-later
 */
class AddDonationValidator {

	private PaymentDataValidator $paymentDataValidator;
	private BankDataValidator $bankDataValidator;
	private IbanBlocklist $ibanBlocklist;
	private AddressValidator $addressValidator;
	private EmailValidator $emailValidator;

	private AddDonationRequest $request;

	/**
	 * @var ConstraintViolation[]
	 */
	private array $violations;

	private array $maximumFieldLengths = [
		Result::SOURCE_TRACKING_SOURCE => 250,
		Result::SOURCE_DONOR_EMAIL => 250
	];

	public function __construct( PaymentDataValidator $paymentDataValidator, BankDataValidator $bankDataValidator,
		IbanBlocklist $ibanBlocklist, EmailValidator $emailValidator, AddressValidator $addressValidator ) {
		$this->paymentDataValidator = $paymentDataValidator;
		$this->bankDataValidator = $bankDataValidator;
		$this->ibanBlocklist = $ibanBlocklist;
		$this->addressValidator = $addressValidator;
		$this->emailValidator = $emailValidator;
	}

	public function validate( AddDonationRequest $addDonationRequest ): Result {
		$this->request = $addDonationRequest;
		$this->violations = [];

		$this->validateAmount();
		$this->validatePayment();
		$this->validateBankData();
		$this->validateDonor();

		return new Result( ...$this->violations );
	}

	private function validateAmount(): void {
		// TODO validate without euro class, put conversion in PaymentDataValidator
		$result = $this->paymentDataValidator->validate(
			$this->request->getAmount()->getEuroFloat(),
			$this->request->getPaymentType()
		);

		$violations = array_map(
			static function ( ConstraintViolation $violation ) {
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

		$this->validateIban( $bankData->getIban() );
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
		$this->validateFieldLength( $this->request->getDonorEmailAddress(), Result::SOURCE_DONOR_EMAIL );
		$donorType = $this->request->getDonorType();
		if ( $donorType->is( DonorType::PERSON() ) ) {
			$this->violations = array_merge(
				$this->violations,
				$this->getPersonNameViolations(),
				$this->getAddressViolations(),
				$this->validateEmail()->getViolations()
			);
		} elseif ( $donorType->is( DonorType::COMPANY() ) ) {
			$this->violations = array_merge(
				$this->violations,
				$this->getCompanyNameViolations(),
				$this->getAddressViolations(),
				$this->validateEmail()->getViolations()
			);
		} elseif ( $donorType->is( DonorType::EMAIL() ) ) {
			$this->violations = array_merge(
				$this->violations,
				$this->getPersonNameViolations(),
				$this->validateEmail()->getViolations()
			);
		}
	}

	private function getPersonNameViolations(): array {
		return $this->addressValidator->validatePersonName(
			$this->request->getDonorSalutation(),
			$this->request->getDonorTitle(),
			$this->request->getDonorFirstName(),
			$this->request->getDonorLastName()
		)->getViolations();
	}

	private function getCompanyNameViolations(): array {
		return $this->addressValidator->validateCompanyName(
			$this->request->getDonorCompany()
		)->getViolations();
	}

	private function getAddressViolations(): array {
		return $this->addressValidator->validatePostalAddress(
			$this->request->getDonorStreetAddress(),
			$this->request->getDonorPostalCode(),
			$this->request->getDonorCity(),
			$this->request->getDonorCountryCode()
		)->getViolations();
	}

	private function validateIban( Iban $iban ): void {
		if ( $this->ibanBlocklist->isIbanBlocked( $iban ) ) {
			$this->addViolations(
				[
					new ConstraintViolation(
						$iban->toString(),
						Result::VIOLATION_IBAN_BLOCKED,
						BankDataValidationResult::SOURCE_IBAN
					) ]
			);
		}
	}

	private function validateEmail(): ValidationResult {
		if ( $this->request->donorIsAnonymous() ) {
			return new ValidationResult();
		}
		return $this->emailValidator->validate( $this->request->getDonorEmailAddress() );
	}

}
