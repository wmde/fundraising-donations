<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * @license GPL-2.0-or-later
 */
class DonationConfirmationMailer {

	private TemplateMailerInterface $mailer;

	public function __construct( TemplateMailerInterface $mailer ) {
		$this->mailer = new BestEffortTemplateMailer( $mailer );
	}

	public function sendConfirmationMailFor( Donation $donation ): void {
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
				'amount' => $donation->getAmount()->getEuroFloat(),
				'interval' => $donation->getPaymentIntervalInMonths(),
				'needsModeration' => $donation->needsModeration(),
				'paymentType' => $donation->getPaymentMethodId(),
				'bankTransferCode' => $this->getBankTransferCode( $donation->getPaymentMethod() ),
				'receiptOptIn' => $donation->getOptsIntoDonationReceipt(),
			]
		];
	}

	private function getBankTransferCode( PaymentMethod $paymentMethod ): string {
		// TODO convert this `if` statement into an interface where every payment method except BankTransfer returns empty string
		// See https://phabricator.wikimedia.org/T192323
		if ( $paymentMethod instanceof BankTransferPayment ) {
			return $paymentMethod->getBankTransferCode();
		}

		return '';
	}
}
