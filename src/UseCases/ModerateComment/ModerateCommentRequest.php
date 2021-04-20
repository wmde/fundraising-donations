<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ModerateComment;

class ModerateCommentRequest {
	private const ACTION_PUBLISH = 'publish';
	private const ACTION_RETRACT = 'retract';

	private int $donationId;
	private string $action;
	private string $authorizedUser;

	private function __construct( int $donationId, string $authorizedUser, string $action ) {
		$this->donationId = $donationId;
		$this->authorizedUser = $authorizedUser;
		$this->action = $action;
	}

	public static function publishComment( int $donationId, string $authorizedUser ): self {
		return new self( $donationId, $authorizedUser, self::ACTION_PUBLISH );
	}

	public static function retractComment( int $donationId, string $authorizedUser ): self {
		return new self( $donationId, $authorizedUser, self::ACTION_RETRACT );
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function shouldPublish(): bool {
		return $this->action === self::ACTION_PUBLISH;
	}

	public function getAuthorizedUser(): string {
		return $this->authorizedUser;
	}
}
