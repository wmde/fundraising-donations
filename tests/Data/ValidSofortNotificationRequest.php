<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;

class ValidSofortNotificationRequest {

	public static function newInstantPayment( int $internalDonationId = 1 ): NotificationRequest {
		return new NotificationRequest( [
			'transactionId' => 'fff-ggg-hhh',
			'valuationDate' => '2022-05-04T04:05:00Z'
		],
		$internalDonationId );
	}
}
