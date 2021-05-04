<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use WMDE\Fundraising\DonationContext\Authorization\TokenSet;
use WMDE\Fundraising\DonationContext\Infrastructure\HttpDonationConfirmationNotifier;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;

/**
 * @covers \WMDE\Fundraising\DonationContext\Infrastructure\HttpDonationConfirmationNotifier
 */
class HttpDonationConfirmationNotifierTest extends TestCase {

	public function testSendConfirmationFor(): void {
		$donation = ValidDonation::newBookedAnonymousPayPalDonationUpdate( 1 );
		$testToken = 'blabla';
		$httpClient = $this->createMock( HttpClientInterface::class );
		$authorizer = new SucceedingDonationAuthorizer();
		$authorizer->setTokenSet( new TokenSet( $testToken, "random value token" ) );
		$endpointUrl = 'https://somefancyendpoint.xyz/';

		$httpClient->expects( $this->once() )->method( 'request' )->with(
			'GET',
			$endpointUrl,
			[ 'query' => [
				'donation_id' => $donation->getId(),
				'update_token' => $testToken
			] ]
		);

		$notifier = new HttpDonationConfirmationNotifier( $authorizer, $httpClient, $endpointUrl );
		$notifier->sendConfirmationFor( $donation );
	}
}
