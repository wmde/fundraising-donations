<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use Symfony\Contracts\HttpClient\HttpClientInterface;
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

	private HttpDonationNotifierUrlAuthorizer $urlAuthorizer;
	private HttpClientInterface $httpClient;
	private string $endpointUrl;

	public function __construct( HttpDonationNotifierUrlAuthorizer $authorizer, HttpClientInterface $httpClient, string $endpointUrl ) {
		$this->urlAuthorizer = $authorizer;
		$this->httpClient = $httpClient;
		$this->endpointUrl = $endpointUrl;
	}

	/**
	 * @param Donation $donation
	 * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
	 */
	public function sendConfirmationFor( Donation $donation ): void {
		$query = [
			'donation_id' => $donation->getId(),
		];
		$query = $this->urlAuthorizer->addAuthorizationParameters( $donation->getId(), $query );
		$this->httpClient->request( 'GET', $this->endpointUrl, [ 'query' => $query ] );
	}

	public function sendModerationNotificationToAdmin( Donation $donation ): void {
		// This method should only be called from AddDonationUseCase,
		// HttpDonationNotifier should not be used for that use case
		throw new \LogicException( 'Method not allowed, check HttpDonationNotifier::sendModerationNotificationToAdmin comment for details.' );
	}

}
