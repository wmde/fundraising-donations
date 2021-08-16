<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetchingException;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;

/**
 * @license GPL-2.0-or-later
 */
class HttpDonationConfirmationNotifier implements DonationConfirmationNotifier {

	private DonationTokenFetcher $tokenFetcher;
	private HttpClientInterface $httpClient;
	private string $endpointUrl;

	public function __construct( DonationTokenFetcher $authorizer, HttpClientInterface $httpClient, string $endpointUrl ) {
		$this->tokenFetcher = $authorizer;
		$this->httpClient = $httpClient;
		$this->endpointUrl = $endpointUrl;
	}

	/**
	 * @param Donation $donation
	 * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
	 * @throws DonationTokenFetchingException
	 */
	public function sendConfirmationFor( Donation $donation ): void {
		$this->httpClient->request(
			'GET',
			$this->endpointUrl,
			[ 'query' => [
				'donation_id' => $donation->getId(),
				'update_token' => $this->tokenFetcher->getTokens( $donation->getId() )->getUpdateToken()
			] ]
		);
	}

}
