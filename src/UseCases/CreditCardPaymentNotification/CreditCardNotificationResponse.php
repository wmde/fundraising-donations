<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification;

use Exception;

/**
 * @license GNU GPL v2+
 */
class CreditCardNotificationResponse {

	public const PAYMENT_TYPE_MISMATCH = 'payment type mismatch';
	public const DONATION_NOT_FOUND = 'donation not found';
	public const DATABASE_ERROR = 'data set could not be retrieved from database';
	public const AMOUNT_MISMATCH = 'amount mismatch';
	public const AUTHORIZATION_FAILED = 'invalid or expired token';
	public const DOMAIN_ERROR = 'data set could not be updated';

	private $errorMessage;
	private $isSuccess;
	private $lowLevelError;

	private const IS_SUCCESS = true;
	private const IS_FAILURE = false;

	public function __construct( bool $isSuccess, string $errorMessage, ?Exception $mailerError = null ) {
		$this->errorMessage = $errorMessage;
		$this->isSuccess = $isSuccess;
		$this->lowLevelError = $mailerError;
	}

	public static function newFailureResponse( string $errorMessage, ?Exception $error = null ): self {
		return new self( self::IS_FAILURE, $errorMessage, $error );
	}

	public static function newSuccessResponse( ?Exception $mailerError ): self {
		return new self( self::IS_SUCCESS, '', $mailerError );
	}

	public function getErrorMessage(): string {
		return $this->errorMessage;
	}

	public function isSuccessful(): bool {
		return $this->isSuccess;
	}

	public function getLowLevelError(): ?Exception {
		return $this->lowLevelError;
	}
}
