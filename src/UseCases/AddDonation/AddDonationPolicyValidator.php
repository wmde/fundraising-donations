<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationValidationResult as Result;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\Validators\AmountPolicyValidator;
use WMDE\FunValidators\Validators\TextPolicyValidator;

/**
 * This validator is for checking if a donation request is valid, but needs moderation / immediate deletion.
 * It will be applied **after** the use case has created the donation.
 *
 * Moderation reasons can be either amounts that are too high (but still plausible) or text policy violations in
 * the email or postal address fields ("bad words", according to a deny- and allow-list).
 *
 * For forbidden email addresses, we immediately delete the donation.
 */
class AddDonationPolicyValidator {

	/**
	 * This is a list of payment type strings where we'll not apply the moderation.
	 *
	 * Currently, it's the list of payments provided by an external payment provider.
	 * The reasoning behind this is that either the payment will be incomplete
	 * (because the user did not complete it on the page of the payment provider),
	 * so it doesn't need to be moderated.
	 *
	 * If the user completes the payment, the moderation rules also don't apply,
	 * because we already got the money. In case of high donations,
	 * the fundraising department has processes in place to handle those.
	 * In case of offensive words, we don't care ;-)
	 */
	private const PAYMENT_TYPES_THAT_SKIP_MODERATION = [ 'PPL', 'MCP', 'SUB' ];

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
		if ( $this->paymentTypeBypassesModeration( $request->getPaymentCreationRequest()->paymentType ) ) {
			return false;
		}
		$violations = array_merge(
			$this->getAmountViolations( $request ),
			$this->getBadWordViolations( $request )
		);

		return !empty( $violations );
	}

	/**
	 * Indicate go-ahead for deleting donations where the email is on the forbidden list.
	 *
	 * When the request indicates that the donor is anonymous, don't check the list.
	 * This behavior ensures that even when the frontend sends form data,
	 * it will not lead to validation for anonymous users.
	 *
	 * @param AddDonationRequest $request
	 * @return bool
	 * @deprecated This has not been used after 2016 and might be removed.
	 */
	public function isAutoDeleted( AddDonationRequest $request ): bool {
		if ( $request->donorIsAnonymous() ) {
			return false;
		}
		foreach ( $this->forbiddenEmailAddresses as $blacklistEntry ) {
			if ( preg_match( $blacklistEntry, $request->getDonorEmailAddress() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate address fields with text policy (allow- and deny lists).
	 *
	 * When the request indicates that the donor is anonymous, skip the validation.
	 * This behavior ensures that even when the frontend sends form data,
	 * it will not lead to validation for anonymous users.
	 *
	 * @param AddDonationRequest $request
	 * @return array
	 */
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

	private function paymentTypeBypassesModeration( string $paymentType ): bool {
		return in_array( $paymentType, self::PAYMENT_TYPES_THAT_SKIP_MODERATION );
	}
}
