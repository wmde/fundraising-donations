<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;

class SucceedingDonationAuthorizerSpy implements DonationAuthorizationChecker {

	private bool $authorizedAsUser = false;
	private bool $authorizedAsAdmin = false;

	public function userCanModifyDonation( int $donationId ): bool {
		$this->authorizedAsUser = true;
		return true;
	}

	public function systemCanModifyDonation( int $donationId ): bool {
		$this->authorizedAsAdmin = true;
		return true;
	}

	public function canAccessDonation( int $donationId ): bool {
		return true;
	}

	public function hasAuthorizedAsUser(): bool {
		return $this->authorizedAsUser;
	}

	public function hasAuthorizedAsAdmin(): bool {
		return $this->authorizedAsAdmin;
	}
}
