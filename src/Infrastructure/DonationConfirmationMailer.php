<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;

/**
 * @license GPL-2.0-or-later
 */
class DonationConfirmationMailer implements DonationConfirmationNotifier {

	private TemplateMailerInterface $mailer;

	public function __construct( TemplateMailerInterface $mailer ) {
		$this->mailer = $mailer;
	}

	public function sendConfirmationFor( Donation $donation ): void {
		if ( !$donation->getDonor()->hasEmailAddress() ) {
			return;
		}
		$this->mailer->sendMail(
			new EmailAddress( $donation->getDonor()->getEmailAddress() ),
			$this->getConfirmationMailTemplateArguments( $donation )
		);
	}

	private function getConfirmationMailTemplateArguments( Donation $donation ): array {
		return [
			'recipient' => $donation->getDonor()->getName()->toArray(),
			'donation' => [
				'id' => $donation->getId(),
				// TODO use "get payment" use case for getting payment information
				'amount' => $donation->getAmount()->getEuroFloat(),
				'interval' => $donation->getPaymentIntervalInMonths(),
				'paymentType' => $donation->getPaymentMethodId(),
				'needsModeration' => $donation->isMarkedForModeration(),
				// TODO use "get payment" use case for getting transfer code, default to empty string
				'bankTransferCode' => '',
				'receiptOptIn' => $donation->getOptsIntoDonationReceipt(),
			]
		];
	}
}
