<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetchingException;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;

/**
 * This class is used in the Fundraising Operation Center for sending
 * confirmation mails when an admin approves a moderated donation.
 *
 * We use it because the FOC can't send e-mails with the right sender.
 * As a workaround, we relay the sending to a special route of the Fundraising App.
 */
class HttpDonationNotifier implements DonationNotifier {

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

	public function sendModerationNotificationToAdmin( Donation $donation ): void {
		// This method should only be called from AddDonationUseCase,
		// HttpDonationNotifier should not be used for that use case
		throw new \LogicException( 'Method not allowed, check HttpDonationNotifier::sendModerationNotificationToAdmin comment for details.' );
	}

}
