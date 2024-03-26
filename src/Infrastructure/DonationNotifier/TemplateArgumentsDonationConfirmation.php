<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier;

class TemplateArgumentsDonationConfirmation {
	/**
	 * @param array<string,string> $recipient Output of Name::toArray()
	 * @param TemplateArgumentsDonation $donation
	 */
	public function __construct(
		public readonly array $recipient,
		public readonly TemplateArgumentsDonation $donation
	) {
	}
}
