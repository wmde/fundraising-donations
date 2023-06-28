<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases\CancelDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest
 */
class CancelDonationRequestTest extends TestCase {

	public function testUnauthorizedRequest(): void {
		$donationID = 1;
		$cancelDonationRequest = new CancelDonationRequest( $donationID );

		$this->assertSame( $donationID, $cancelDonationRequest->getDonationId() );
		$this->assertFalse( $cancelDonationRequest->isAuthorizedRequest() );
	}

	public function testAuthorizedRequest(): void {
		$donationID = 2;
		$authUser = "adminUserX";
		$cancelDonationRequest = new CancelDonationRequest( $donationID, $authUser );

		$this->assertSame( $donationID, $cancelDonationRequest->getDonationId() );
		$this->assertTrue( $cancelDonationRequest->isAuthorizedRequest() );
		$this->assertSame( $authUser, $cancelDonationRequest->getUserName() );
	}

	public function testUnauthorizedRequestHasNoUserName(): void {
		$donationID = 70;
		$cancelDonationRequest = new CancelDonationRequest( $donationID );

		$this->assertSame( $donationID, $cancelDonationRequest->getDonationId() );
		$this->expectException( \LogicException::class );
		$cancelDonationRequest->getUserName();
	}
}
