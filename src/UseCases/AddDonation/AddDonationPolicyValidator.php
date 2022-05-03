<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult as Result;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\Validators\AmountPolicyValidator;
use WMDE\FunValidators\Validators\TextPolicyValidator;

class AddDonationPolicyValidator {

	private AmountPolicyValidator $amountPolicyValidator;
	private TextPolicyValidator $textPolicyValidator;
	/**
	 * @var string[]
	 */
	private array $forbiddenEmailAddresses;

	public function __construct( AmountPolicyValidator $amountPolicyValidator, TextPolicyValidator $textPolicyValidator,
		array $forbiddenEmailAddresses = [] ) {
		$this->amountPolicyValidator = $amountPolicyValidator;
		$this->textPolicyValidator = $textPolicyValidator;
		$this->forbiddenEmailAddresses = $forbiddenEmailAddresses;
	}

	public function needsModeration( AddDonationRequest $request ): bool {
		$violations = array_merge(
			$this->getAmountViolations( $request ),
			$this->getBadWordViolations( $request )
		);

		return !empty( $violations );
	}

	public function isAutoDeleted( AddDonationRequest $request ): bool {
		foreach ( $this->forbiddenEmailAddresses as $blacklistEntry ) {
			if ( preg_match( $blacklistEntry, $request->getDonorEmailAddress() ) ) {
				return true;
			}
		}

		return false;
	}

	private function getBadWordViolations( AddDonationRequest $request ): array {
		if ( $request->donorIsAnonymous() ) {
			return [];
		}

		return array_merge(
			$this->getPolicyViolationsForField( $request->getDonorFirstName(), Result::SOURCE_DONOR_FIRST_NAME ),
			$this->getPolicyViolationsForField( $request->getDonorLastName(), Result::SOURCE_DONOR_LAST_NAME ),
			$this->getPolicyViolationsForField( $request->getDonorCompany(), Result::SOURCE_DONOR_COMPANY ),
			$this->getPolicyViolationsForField(
				$request->getDonorStreetAddress(),
				Result::SOURCE_DONOR_STREET_ADDRESS
			),
			$this->getPolicyViolationsForField( $request->getDonorCity(), Result::SOURCE_DONOR_CITY )
		);
	}

	private function getPolicyViolationsForField( string $fieldContent, string $fieldName ): array {
		if ( $fieldContent === '' ) {
			return [];
		}
		if ( $this->textPolicyValidator->textIsHarmless( $fieldContent ) ) {
			return [];
		}
		return [ new ConstraintViolation( $fieldContent, Result::VIOLATION_TEXT_POLICY, $fieldName ) ];
	}

	private function getAmountViolations( AddDonationRequest $request ): array {
		$paymentRequest = $request->getPaymentCreationRequest();
		return array_map(
			static function ( ConstraintViolation $violation ) {
				$violation->setSource( Result::SOURCE_PAYMENT_AMOUNT );
				return $violation;
			},
			$this->amountPolicyValidator->validate(
				$paymentRequest->amountInEuroCents / 100,
				$paymentRequest->interval
			)->getViolations()
		);
	}
}
