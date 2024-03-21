<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

class DonationMailer implements DonationNotifier {

	public function __construct(
		private readonly TemplateMailerInterface $confirmationMailer,
		private readonly TemplateMailerInterface $adminMailer,
		private readonly GetPaymentUseCase $getPaymentService,
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

	/**
	 * @param Donation $donation
	 *
	 * @return array{recipient:array<string,string>,donation:array<string,mixed>}
	 */
	private function getTemplateArguments( Donation $donation ): array {
		$paymentInfo = $this->getPaymentService->getPaymentDataArray( $donation->getPaymentId() );
		return [
			'recipient' => $donation->getDonor()->getName()->toArray(),
			'donation' => [
				'id' => $donation->getId(),
				'amount' => Euro::newFromCents( (int)$paymentInfo['amount'] )->getEuroFloat(),
				'amountInCents' => $paymentInfo['amount'],
				'interval' => $paymentInfo['interval'],
				'paymentType' => $paymentInfo['paymentType'],
				'needsModeration' => $donation->isMarkedForModeration(),
				'moderationFlags' => $this->getModerationFlags( ...$donation->getModerationReasons() ),
				'bankTransferCode' => $paymentInfo['paymentReferenceCode'] ?? '',
				'receiptOptIn' => $donation->getDonor()->wantsReceipt(),
			]
		];
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

	public function sendModerationNotificationToAdmin( Donation $donation ): void {
		$importantReasons = array_filter(
			$donation->getModerationReasons(),
			fn ( $moderationReason ) => $moderationReason->getModerationIdentifier() === ModerationIdentifier::AMOUNT_TOO_HIGH
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
		$paymentInfo = $this->getPaymentService->getPaymentDataArray( $donation->getPaymentId() );
		return [
			'id' => $donation->getId(),
			'moderationFlags' => $this->getModerationFlags( ...$donation->getModerationReasons() ),
			'amount' => Euro::newFromCents( (int)$paymentInfo['amount'] )->getEuroFloat()
		];
	}

}
