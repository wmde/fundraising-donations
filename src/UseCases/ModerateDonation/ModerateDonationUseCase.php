<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ModerateDonation;

use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;

class ModerateDonationUseCase {

	private const LOG_MESSAGE_DONATION_MARKED_FOR_MODERATION = 'marked for moderation by user: %s';
	private const LOG_MESSAGE_DONATION_MARKED_AS_APPROVED = 'marked as approved by user: %s';

	private DonationRepository $donationRepository;
	private DonationEventLogger $donationLogger;
	private DonationConfirmationNotifier $notifier;

	public function __construct( DonationRepository $donationRepository, DonationEventLogger $donationLogger, DonationConfirmationNotifier $notifier ) {
		$this->donationRepository = $donationRepository;
		$this->donationLogger = $donationLogger;
		$this->notifier = $notifier;
	}

	public function markDonationAsModerated( int $donationId, string $authorizedUser ): ModerateDonationResponse {
		$donation = $this->donationRepository->getDonationById( $donationId );
		if ( $donation === null ) {
			return $this->newModerationFailureResponse( $donationId );
		}
		if ( $donation->isMarkedForModeration() ) {
			return $this->newModerationFailureResponse( $donationId );
		}

		$donation->markForModeration();
		$this->donationRepository->storeDonation( $donation );
		$this->donationLogger->log( $donationId, sprintf( self::LOG_MESSAGE_DONATION_MARKED_FOR_MODERATION, $authorizedUser ) );

		return $this->newModerationSuccessResponse( $donationId );
	}

	public function approveDonation( int $donationId, string $authorizedUser ): ModerateDonationResponse {
		$donation = $this->donationRepository->getDonationById( $donationId );
		if ( $donation === null ) {
			return $this->newModerationFailureResponse( $donationId );
		}
		if ( !$donation->isMarkedForModeration() ) {
			return $this->newModerationFailureResponse( $donationId );
		}
		$donation->approve();
		$this->donationRepository->storeDonation( $donation );
		$this->donationLogger->log( $donationId, sprintf( self::LOG_MESSAGE_DONATION_MARKED_AS_APPROVED, $authorizedUser ) );
		$this->notifier->sendConfirmationFor( $donation );

		return $this->newModerationSuccessResponse( $donationId );
	}

	private function newModerationFailureResponse( int $donationId ): ModerateDonationResponse {
		return new ModerateDonationResponse(
			$donationId,
			ModerateDonationResponse::FAILURE
		);
	}

	private function newModerationSuccessResponse( int $donationId ): ModerateDonationResponse {
		return new ModerateDonationResponse(
			$donationId,
			ModerateDonationResponse::SUCCESS
		);
	}

}
