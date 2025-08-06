<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\RestoreDonation;

use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;

class RestoreDonationUseCase {

	private const string LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN = 'restored by user: %s';

	private DonationRepository $donationRepository;
	private DonationEventLogger $donationLogger;

	public function __construct( DonationRepository $donationRepository, DonationEventLogger $donationLogger ) {
		$this->donationRepository = $donationRepository;
		$this->donationLogger = $donationLogger;
	}

	public function restoreCancelledDonation( int $donationId, string $authorizedUser ): RestoreDonationSuccessResponse|RestoreDonationFailureResponse {
		$donation = $this->donationRepository->getDonationById( $donationId );
		if ( $donation === null ) {
			return new RestoreDonationFailureResponse( $donationId, RestoreDonationFailureResponse::DONATION_NOT_FOUND );
		}
		if ( !$donation->isCancelled() ) {
			return new RestoreDonationFailureResponse( $donationId, RestoreDonationFailureResponse::DONATION_NOT_CANCELED );
		}

		$donation->revokeCancellation();
		$this->donationRepository->storeDonation( $donation );
		$this->donationLogger->log( $donationId, sprintf( self::LOG_MESSAGE_DONATION_STATUS_CHANGE_BY_ADMIN, $authorizedUser ) );

		return new RestoreDonationSuccessResponse( $donationId );
	}
}
