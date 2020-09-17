<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class DonationConfirmationMailer {

	private $mailer;

	public function __construct( TemplateMailerInterface $mailer ) {
		$this->mailer = $mailer;
	}

	public function sendConfirmationMailFor( Donation $donation ): void {
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
		if ( $paymentMethod instanceof BankTransferPayment ) {
			return $paymentMethod->getBankTransferCode();
		}

		return '';
	}
}
