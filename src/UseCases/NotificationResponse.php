<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases;

class NotificationResponse {

	private const DONATION_NOT_FOUND_MESSAGE = 'Donation not found';

	private function __construct( private readonly string $message = '' ) {
	}

	public static function newSuccessResponse(): self {
		return new self( '' );
	}

	public static function newFailureResponse( string $message ): self {
		if ( $message === '' ) {
			throw new \DomainException( 'Failure response must not be empty' );
		}
		return new self( $message );
	}

	public static function newDonationNotFoundResponse(): self {
		return new self( self::DONATION_NOT_FOUND_MESSAGE );
	}

	public function notificationWasHandled(): bool {
		return $this->message === '';
	}

	public function hasErrors(): bool {
		return $this->message !== '';
	}

	public function getMessage(): string {
		return $this->message;
	}

	public function donationWasNotFound(): bool {
		return $this->message === self::DONATION_NOT_FOUND_MESSAGE;
	}
}
