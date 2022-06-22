<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Euro\Euro;
use WMDE\Fundraising\PaymentContext\Domain\DomainSpecificPaymentValidator;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\PaymentType;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResponse;

/**
 * This implements the donation-specific payment validation for the payment domain.
 *
 * It performs payment-specific validations with the domain objects of the Payment domain.
 */
class DonationPaymentValidator implements DomainSpecificPaymentValidator {

	public const MINIMUM_AMOUNT_IN_EUROS = 1;

	/** Absolute maximum amount (inclusive). We don't accept donations that are equal or higher,
	 *  because we don't expect donations *that* big through our form.
	 *
	 * The {@see ModerationService} will flag suspiciously high amounts *below* this
	 * validator threshold for moderation.
	 */
	public const MAXIMUM_AMOUNT_IN_EUROS = 100_000;

	/**
	 * Violation identifier for {@see ConstraintViolation}
	 */
	public const AMOUNT_TOO_LOW = 'donation_amount_too_low';

	/**
	 * Violation identifier for {@see ConstraintViolation}
	 */
	public const AMOUNT_TOO_HIGH = 'donation_amount_too_high';

	/**
	 * Error source name for {@see ConstraintViolation}
	 */
	public const SOURCE_AMOUNT = 'amount';

	private Euro $minimumAmount;
	private Euro $maximumAmount;

	public function __construct() {
		$this->minimumAmount = Euro::newFromInt( self::MINIMUM_AMOUNT_IN_EUROS );
		$this->maximumAmount = Euro::newFromInt( self::MAXIMUM_AMOUNT_IN_EUROS );
	}

	/**
	 * Check if amount is too high or low.
	 *
	 * We're ignoring the payment interval, we don't calculate the yearly amount
	 *
	 * @param Euro $amount
	 * @param PaymentInterval $interval
	 * @param PaymentType $paymentType Ignored, we allow all types and don't depend on the type for min/max amounts
	 * @return ValidationResponse
	 */
	public function validatePaymentData( Euro $amount, PaymentInterval $interval, PaymentType $paymentType ): ValidationResponse {
		$amountInCents = $amount->getEuroCents();

		if ( $amountInCents < $this->minimumAmount->getEuroCents() ) {
			return ValidationResponse::newFailureResponse( [
				new ConstraintViolation( $amountInCents, self::AMOUNT_TOO_LOW, self::SOURCE_AMOUNT )
			] );
		}

		if ( $amountInCents >= $this->maximumAmount->getEuroCents() ) {
			return ValidationResponse::newFailureResponse( [
				new ConstraintViolation( $amountInCents, self::AMOUNT_TOO_HIGH, self::SOURCE_AMOUNT )
			] );
		}

		return ValidationResponse::newSuccessResponse();
	}
}
