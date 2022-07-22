<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;

/**
 * @license GPL-2.0-or-later
 */
class DonationMailer implements DonationNotifier {

	public function __construct(
		private readonly TemplateMailerInterface $confirmationMailer,
		private readonly TemplateMailerInterface $adminMailer,
		private readonly string $adminEmailAddress
	) {
	}

	public function sendConfirmationFor( Donation $donation ): void {
		if ( !$donation->getDonor()->hasEmailAddress() ) {
			return;
		}
		$this->confirmationMailer->sendMail(
			new EmailAddress( $donation->getDonor()->getEmailAddress() ),
			$this->getTemplateArguments( $donation )
		);
	}

	private function getTemplateArguments( Donation $donation ): array {
		return [
			'recipient' => $donation->getDonor()->getName()->toArray(),
			'donation' => [
				'id' => $donation->getId(),
				'amount' => $donation->getAmount()->getEuroFloat(),
				'interval' => $donation->getPaymentIntervalInMonths(),
				'needsModeration' => $donation->isMarkedForModeration(),
				'moderationFlags' => $this->getModerationFlags( ...$donation->getModerationReasons() ),
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

	/**
	 * @param ModerationReason ...$getModerationReasons
	 * @return array<string,boolean>
	 */
	private function getModerationFlags( ModerationReason ...$getModerationReasons ): array {
		$result = [];
		foreach ( $getModerationReasons as $reason ) {
			$reasonName = $reason->getModerationIdentifier()->name;
			$result[$reasonName] = true;
		}
		return $result;
	}

	public function sendModerationNotificationToAdmin( Donation $donation ) {
		$importantReasons = array_filter(
			$donation->getModerationReasons(),
			fn( $moderationReason ) => $moderationReason->getModerationIdentifier() === ModerationIdentifier::AMOUNT_TOO_HIGH
		);
		if ( count( $importantReasons ) === 0 ) {
			return;
		}
		$this->adminMailer->sendMail(
			new EmailAddress( $this->adminEmailAddress ),
			$this->getAdminTemplateArguments( $donation )
		);
	}

	/**
	 * @param Donation $donation
	 * @return array{id:int,moderationFlags:array<string,boolean>,amount:float}
	 */
	private function getAdminTemplateArguments( Donation $donation ): array {
		return [
			'id' => $donation->getId(),
			'moderationFlags' => $this->getModerationFlags( ...$donation->getModerationReasons() ),
			'amount' => $donation->getAmount()->getEuroFloat()
		];
	}

}
