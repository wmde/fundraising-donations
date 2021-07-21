<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddComment;

use WMDE\FreezableValueObject\FreezableValueObject;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class AddCommentRequest {
	use FreezableValueObject;

	private string $commentText;
	private bool $isPublic;
	private bool $isAnonymous;
	private int $donationId;

	public function getCommentText(): string {
		return $this->commentText;
	}

	public function setCommentText( string $commentText ): self {
		$this->assertIsWritable();
		$this->commentText = trim( $commentText );
		return $this;
	}

	public function isPublic(): bool {
		return $this->isPublic;
	}

	public function setIsPublic( bool $isPublic ): self {
		$this->assertIsWritable();
		$this->isPublic = $isPublic;
		return $this;
	}

	public function isAnonymous(): bool {
		return $this->isAnonymous;
	}

	public function setIsAnonymous(): self {
		$this->assertIsWritable();
		$this->isAnonymous = true;
		return $this;
	}

	public function setIsNamed(): self {
		$this->assertIsWritable();
		$this->isAnonymous = false;
		return $this;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function setDonationId( int $donationId ): self {
		$this->assertIsWritable();
		$this->donationId = $donationId;
		return $this;
	}

}
