<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\GetDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizationChecker;

class GetDonationUseCase {

	/**
	 * @param DonationAuthorizationChecker $authorizer
	 * @param DonationRepository $donationRepository
	 */
	public function __construct(
		private readonly DonationAuthorizationChecker $authorizer,
		private readonly DonationRepository $donationRepository ) {
	}

	public function showConfirmation( GetDonationRequest $request ): GetDonationResponse {
		if ( !$this->authorizer->canAccessDonation( $request->getDonationId() ) ) {
			return GetDonationResponse::newNotAllowedResponse();
		}

		$donation = $this->getDonationById( $request->getDonationId() );

		if ( $donation === null || $donation->isCancelled() ) {
			return GetDonationResponse::newNotAllowedResponse();
		}

		return GetDonationResponse::newValidResponse(
			// TODO: create a DTO to not expose the Donation Entity beyond the UC layer
			$donation
		);
	}

	private function getDonationById( int $donationId ): ?Donation {
		try {
			return $this->donationRepository->getDonationById( $donationId );
		} catch ( GetDonationException $ex ) {
			return null;
		}
	}

}
