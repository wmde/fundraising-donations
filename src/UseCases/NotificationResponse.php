<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases;

class NotificationResponse {

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

	public function notificationWasHandled(): bool {
		return $this->message === '';
	}

	public function hasErrors(): bool {
		return $this->message !== '';
	}

	public function getMesssage(): string {
		return $this->message;
	}
}
