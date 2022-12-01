<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor;

trait ReceiptTrait {
	private bool $receipt;

	public function requireReceipt(): void {
		$this->receipt = true;
	}

	public function declineReceipt(): void {
		$this->receipt = false;
	}

	public function wantsReceipt(): bool {
		return $this->receipt;
	}
}
