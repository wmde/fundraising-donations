<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsAdmin;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsDonation;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationNotifier\TemplateArgumentsDonationConfirmation;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\UseCases\GetPayment\GetPaymentUseCase;

/**
 * This class transforms the Donation and its Payment objects into TemplateArgument transfer objects.
 *
 * It also implements the following notification strategy:
 *  - anonymous donors don't get a confirmation
 *  - admins only get a notification if the donation's moderation reasons contain an "amount too high" reason
 */
class TemplateDonationNotifier implements DonationNotifier {

	public function __construct(
		private readonly DonorNotificationInterface $confirmationMailer,
		private readonly AdminNotificationInterface $adminMailer,
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
			$this->getTemplateArgumentsForDonorConfirmation( $donation )
		);
	}

	private function getTemplateArgumentsForDonorConfirmation( Donation $donation ): TemplateArgumentsDonationConfirmation {
		$paymentInfo = $this->getPaymentService->getPaymentDataArray( $donation->getPaymentId() );
		return new TemplateArgumentsDonationConfirmation(
			$donation->getDonor()->getName()->toArray(),
			new TemplateArgumentsDonation(
				id: $donation->getId(),
				amount: Euro::newFromCents( (int)$paymentInfo['amount'] )->getEuroFloat(),
				amountInCents: intval( $paymentInfo['amount'] ),
				interval: intval( $paymentInfo['interval'] ),
				paymentType: strval( $paymentInfo['paymentType'] ),
				needsModeration: $donation->isMarkedForModeration(),
				moderationFlags: $this->getModerationFlags( ...$donation->getModerationReasons() ),
				bankTransferCode: strval( $paymentInfo['paymentReferenceCode'] ?? '' ),
				receiptOptIn: $donation->getDonor()->wantsReceipt(),
			)
		);
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

	private function getAdminTemplateArguments( Donation $donation ): TemplateArgumentsAdmin {
		$paymentInfo = $this->getPaymentService->getPaymentDataArray( $donation->getPaymentId() );
		return new TemplateArgumentsAdmin(
			donationId: $donation->getId(),
			moderationFlags: $this->getModerationFlags( ...$donation->getModerationReasons() ),
			amount: Euro::newFromCents( (int)$paymentInfo['amount'] )->getEuroFloat()
		);
	}

}
