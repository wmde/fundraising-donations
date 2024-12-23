<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;

/**
 * This is the name for anonymous donors.
 */
class NoName implements DonorName {

	/**
	 * The name displayed in {@see self::getFullName()}
	 *
	 * For historical reasons, this is German and won't follow our translation key rules (kebab-case) in the foreseeable future.
	 * You can use it as a translation key in the frontend, though.
	 *
	 * This is mostly used for displaying the confirmation page and when creating comments.
	 */
	public const DISPLAY_NAME = 'Anonym';

	public function getFullName(): string {
		return self::DISPLAY_NAME;
	}

	public function toArray(): array {
		return [];
	}

	public function getSalutation(): string {
		return '';
	}

}
