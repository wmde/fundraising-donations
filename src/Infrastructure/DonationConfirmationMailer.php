<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * @license GNU GPL v2+
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
			'recipient' => [
				'lastName' => $donation->getDonor()->getName()->getLastName(),
				'salutation' => $donation->getDonor()->getName()->getSalutation(),
				'title' => $donation->getDonor()->getName()->getTitle()
			],
			'donation' => [
				'id' => $donation->getId(),
				'amount' => $donation->getAmount()->getEuroFloat(), // number is formatted in template
				'interval' => $donation->getPaymentIntervalInMonths(),
				'needsModeration' => $donation->needsModeration(),
				'paymentType' => $donation->getPaymentMethodId(),
				'bankTransferCode' => $this->getBankTransferCode( $donation->getPaymentMethod() ),
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
