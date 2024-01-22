<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AbstractDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\MailingListTrait;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\ReceiptTrait;

/**
 * This class is for testing "this should never happen" code in the DonorFieldMapper donor type checking.
 */
class FakeDonor extends AbstractDonor {
	use MailingListTrait;
	use ReceiptTrait;

	public function isPrivatePerson(): bool {
		return false;
	}

	public function isCompany(): bool {
		return false;
	}

	public function getDonorType(): string {
		return 'Just testing';
	}

}
