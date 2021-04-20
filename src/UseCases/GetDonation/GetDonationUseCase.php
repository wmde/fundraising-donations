<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\GetDonation;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class GetDonationUseCase {

	private $authorizer;
	private $tokenFetcher;
	private $donationRepository;

	public function __construct( DonationAuthorizer $authorizer, DonationTokenFetcher $tokenFetcher,
		DonationRepository $donationRepository ) {
		$this->authorizer = $authorizer;
		$this->tokenFetcher = $tokenFetcher;
		$this->donationRepository = $donationRepository;
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
			$donation,
			$this->tokenFetcher->getTokens( $request->getDonationId() )->getUpdateToken()
		);
	}

	private function getDonationById( int $donationId ): ?Donation {
		try {
			return $this->donationRepository->getDonationById( $donationId );
		}
		catch ( GetDonationException $ex ) {
			return null;
		}
	}

}
