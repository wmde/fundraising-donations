<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Repositories;

use DateTime;
use WMDE\FreezableValueObject\FreezableValueObject;

class CommentWithAmount {
	use FreezableValueObject;

	private string $authorName;
	private float $donationAmount;
	private string $commentText;
	private DateTime $donationTime;
	private int $donationId;

	public static function newInstance(): self {
		return new self();
	}

	private function __construct() {
	}

	public function getAuthorName(): string {
		return $this->authorName;
	}

	public function getDonationAmount(): float {
		return $this->donationAmount;
	}

	public function getCommentText(): string {
		return $this->commentText;
	}

	public function getDonationTime(): DateTime {
		return $this->donationTime;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

	public function setAuthorName( string $authorName ): self {
		$this->assertIsWritable();
		$this->authorName = $authorName;
		return $this;
	}

	public function setDonationAmount( float $donationAmount ): self {
		$this->assertIsWritable();
		$this->donationAmount = $donationAmount;
		return $this;
	}

	public function setCommentText( string $commentText ): self {
		$this->assertIsWritable();
		$this->commentText = $commentText;
		return $this;
	}

	public function setDonationTime( DateTime $donationTime ): self {
		$this->assertIsWritable();
		$this->donationTime = $donationTime;
		return $this;
	}

	public function setDonationId( int $donationId ): self {
		$this->assertIsWritable();
		$this->donationId = $donationId;
		return $this;
	}

}
