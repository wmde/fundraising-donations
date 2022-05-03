<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Euro\Euro;
use WMDE\Fundraising\PaymentContext\Domain\DomainSpecificPaymentValidator;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\FunValidators\ConstraintViolation;
use WMDE\FunValidators\ValidationResponse;

/**
 * This implements the donation-specific payment validation for the payment domain.
 *
 * This class must be injected into the {@see PaymentValidator instance}, because
 * we have inverted the domain-specific validation of payments with the
 * {@see DomainSpecificPaymentValidator} interface.
 *
 * The overall class/dependency hierarchy looks like this:
 *
 * AddDonationUseCase -> CreatePaymentUseCase -> PaymentValidator -> (DomainSpecificPaymentValidator)
 *
 * "->" means "depends on" (as a constructor parameter)
 *
 * DonationPaymentValidator is an implementation of DomainSpecificPaymentValidator.
 * Other domains (e.g. memberships) will have their own rules on minimum and maximum amounts.
 *
 */
class DonationPaymentValidator implements DomainSpecificPaymentValidator {
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

	/**
	 * @param int $minimumAmountInEuros
	 * @param int $maximumAmountInEuros Absolute maximum amount. We don't accept donations that are higher,
	 *          because we don't expect donations *that* big through our form.
	 *          The {@see AddDonationPolicyValidator} will flag suspiciously high amounts below this
	 *          validator threshold for moderation.
	 */
	public function __construct(
		int $minimumAmountInEuros,
		int $maximumAmountInEuros
	) {
		$this->minimumAmount = Euro::newFromInt( $minimumAmountInEuros );
		$this->maximumAmount = Euro::newFromInt( $maximumAmountInEuros );
	}

	/**
	 * Check if amount is too high or low.
	 *
	 * We're ignoring the payment interval, we don't calculate the yearly amount
	 *
	 * @param Euro $amount
	 * @param PaymentInterval $interval
	 * @return ValidationResponse
	 */
	public function validatePaymentData( Euro $amount, PaymentInterval $interval ): ValidationResponse {
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
