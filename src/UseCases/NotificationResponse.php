<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\UseCases;

class NotificationResponse {

	private bool $wasHandled;
	private bool $notificationFailed;
	private string $message;

	private function __construct( bool $notificationWasHandled, bool $isError, string $message = '' ) {
		$this->wasHandled = $notificationWasHandled;
		$this->notificationFailed = $isError;
		$this->message = $message;
	}

	public static function newSuccessResponse(): self {
		return new self( true, false );
	}

	/**
	 * @todo Check if this method is really required when booking Use Cases are refactored
	 *
	 * @param string $message
	 *
	 * @return self
	 */
	public static function newUnhandledResponse( string $message ): self {
		return new self( false, false, $message );
	}

	public static function newFailureResponse( string $message ): self {
		return new self( false, true, $message );
	}

	public function notificationWasHandled(): bool {
		return $this->wasHandled;
	}

	public function hasErrors(): bool {
		return $this->notificationFailed;
	}

	public function getMesssage(): string {
		return $this->message;
	}
}
