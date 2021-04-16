<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CancelDonation;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Infrastructure\TemplateMailerInterface;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CancelDonationUseCase {

	private const LOG_MESSAGE_DONATION_STATUS_CHANGE = 'frontend: storno';
	private const LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN = 'cancelled by user: %s';

	private $donationRepository;
	private $mailer;
	private $authorizationService;
	private $donationLogger;

	public function __construct( DonationRepository $donationRepository, TemplateMailerInterface $mailer,
		DonationAuthorizer $authorizationService, DonationEventLogger $donationLogger ) {
		$this->donationRepository = $donationRepository;
		$this->mailer = $mailer;
		$this->authorizationService = $authorizationService;
		$this->donationLogger = $donationLogger;
	}

	public function cancelDonation( CancelDonationRequest $cancellationRequest ): CancelDonationResponse {
		if ( !$this->requestIsAllowedToModifyDonation( $cancellationRequest ) ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		try {
			$donation = $this->donationRepository->getDonationById( $cancellationRequest->getDonationId() );
		}
		catch ( GetDonationException $ex ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		if ( $donation === null ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		try {
			$donation->cancel();
		}
		catch ( \RuntimeException $ex ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		try {
			$this->donationRepository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return $this->newFailureResponse( $cancellationRequest );
		}

		$this->donationLogger->log( $donation->getId(), $this->getLogMessage( $cancellationRequest ) );

		try {
			$this->sendConfirmationEmail( $cancellationRequest, $donation );
		}
		catch ( \RuntimeException $ex ) {
			return new CancelDonationResponse(
				$cancellationRequest->getDonationId(),
				CancelDonationResponse::MAIL_DELIVERY_FAILED
			);
		}

		return $this->newSuccessResponse( $cancellationRequest );
	}

	public function getLogMessage( CancelDonationRequest $cancellationRequest ): string {
		if ( $cancellationRequest->isAuthorizedRequest() ) {
			return sprintf( self::LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN, $cancellationRequest->getUserName() );
		}
		return self::LOG_MESSAGE_DONATION_STATUS_CHANGE;
	}

	public function requestIsAllowedToModifyDonation( CancelDonationRequest $cancellationRequest ): bool {
		if ( $cancellationRequest->isAuthorizedRequest() ) {
			return $this->authorizationService->systemCanModifyDonation( $cancellationRequest->getDonationId() );

		}
		return $this->authorizationService->userCanModifyDonation( $cancellationRequest->getDonationId() );
	}

	private function newFailureResponse( CancelDonationRequest $cancellationRequest ): CancelDonationResponse {
		return new CancelDonationResponse(
			$cancellationRequest->getDonationId(),
			CancelDonationResponse::FAILURE
		);
	}

	private function newSuccessResponse( CancelDonationRequest $cancellationRequest ): CancelDonationResponse {
		return new CancelDonationResponse(
			$cancellationRequest->getDonationId(),
			CancelDonationResponse::SUCCESS
		);
	}

	private function sendConfirmationEmail( CancelDonationRequest $cancellationRequest, Donation $donation ): void {
		if ( $cancellationRequest->isAuthorizedRequest() ) {
			return;
		}
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
			'donationId' => $donation->getId(),

			'recipient' => $donation->getDonor()->getName()->toArray(),
		];
	}

}
