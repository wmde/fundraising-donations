<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use WMDE\Fundraising\DonationContext\Infrastructure\HttpDonationNotifier;
use WMDE\Fundraising\DonationContext\Infrastructure\HttpDonationNotifierUrlAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;

#[CoversClass( HttpDonationNotifier::class )]
class HttpDonationConfirmationNotifierTest extends TestCase {

	public function testSendConfirmationFor(): void {
		$donation = ValidDonation::newBookedAnonymousPayPalDonationUpdate( 1 );
		$urlAuthorizer = $this->createStub( HttpDonationNotifierUrlAuthorizer::class );
		$urlAuthorizer->method( 'addAuthorizationParameters' )->willReturnArgument( 1 );
		$httpClient = $this->createMock( HttpClientInterface::class );
		$endpointUrl = 'https://somefancyendpoint.xyz/';

		$httpClient->expects( $this->once() )->method( 'request' )->with(
			'GET',
			$endpointUrl,
			[ 'query' => [
				'donation_id' => $donation->getId(),
			] ]
		);

		$notifier = new HttpDonationNotifier( $urlAuthorizer, $httpClient, $endpointUrl );
		$notifier->sendConfirmationFor( $donation );
	}
}
