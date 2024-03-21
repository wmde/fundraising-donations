<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

class DonationComment {

	private string $commentText;
	private bool $isPublic;
	private string $authorDisplayName;

	public function __construct( string $commentText, bool $isPublic, string $authorDisplayName ) {
		$this->commentText = $commentText;
		$this->isPublic = $isPublic;
		$this->authorDisplayName = $authorDisplayName;
	}

	public function getAuthorDisplayName(): string {
		return $this->authorDisplayName;
	}

	public function getCommentText(): string {
		return $this->commentText;
	}

	public function isPublic(): bool {
		return $this->isPublic;
	}

	public function publish(): void {
		$this->isPublic = true;
	}

	public function retract(): void {
		$this->isPublic = false;
	}
}
