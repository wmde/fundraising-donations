<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

class DonationConfirmationMailer implements DonationConfirmationNotifier {

	public function __construct(
		private readonly TemplateMailerInterface $mailer,
		private readonly GetPaymentUseCase $getPaymentService
	) {
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
		$paymentInfo = $this->getPaymentService->getPaymentDataArray( $donation->getPaymentId() );
		return [
			'recipient' => $donation->getDonor()->getName()->toArray(),
			'donation' => [
				'id' => $donation->getId(),
				'amount' => $paymentInfo['amount'],
				'interval' => $paymentInfo['interval'],
				'paymentType' => $paymentInfo['paymentType'],
				'needsModeration' => $donation->isMarkedForModeration(),
				'bankTransferCode' => $paymentInfo['ueb_code'],
				'receiptOptIn' => $donation->getOptsIntoDonationReceipt(),
			]
		];
	}
}
