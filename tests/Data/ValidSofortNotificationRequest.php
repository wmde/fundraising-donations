<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use DateTime;
use WMDE\Fundraising\PaymentContext\RequestModel\SofortNotificationRequest;

class ValidSofortNotificationRequest {

	public static function newInstantPayment( int $internalDonationId = 1 ): SofortNotificationRequest {
		$request = new SofortNotificationRequest();
		$request->setDonationId( $internalDonationId );
		$request->setTime( new DateTime() );
		$request->setTransactionId( 'fff-ggg-hhh' );

		return $request;
	}
}
