<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;

/**
 * @license GPL-2.0-or-later
 */
class HttpDonationConfirmationNotifier implements DonationConfirmationNotifier {

	private DonationAuthorizer $authorizer;
	private HttpClientInterface $httpClient;
	private string $endpointUrl;

	public function __construct( DonationAuthorizer $authorizer, HttpClientInterface $httpClient, string $endpointUrl ) {
		$this->authorizer = $authorizer;
		$this->httpClient = $httpClient;
		$this->endpointUrl = $endpointUrl;
	}

	public function sendConfirmationFor( Donation $donation ): void {
		$this->httpClient->request(
			'GET',
			$this->endpointUrl,
			[ 'query' => [
				'donation_id' => $donation->getId(),
				'update_token' => $this->authorizer->getTokensForDonation( $donation->getId() )->getUpdateToken()
			] ]
		);
	}

}
