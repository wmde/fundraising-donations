<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;

class ValidCreditCardNotificationRequest {

	public const AMOUNT = 1337;
	public const PAYMENT_ID = 'customer.prefix-ID2tbnag4a9u';
	public const CUSTOMER_ID = 'e20fb9d5281c1bca1901c19f6e46213191bb4c17';
	public const SESSION_ID = 'CC13064b2620f4028b7d340e3449676213336a4d';
	public const AUTH_ID = 'd1d6fae40cf96af52477a9e521558ab7';
	public const TOKEN = 'my_secret_token';
	public const UPDATE_TOKEN = 'my_secret_update_token';
	public const TITLE = 'Your generous donation';
	public const COUNTRY_CODE = 'DE';
	public const CURRENCY_CODE = 'EUR';
	public const NOTIFICATION_TYPE_BILLING = 'billing';

	public static function newBillingNotification( int $donationId ): NotificationRequest {
		return new NotificationRequest(
			array_merge(
				self::newBaseBookingData(),
				[
					'function' => self::NOTIFICATION_TYPE_BILLING
				]
			),
			$donationId
		);
	}

	/**
	 * @return array<string,string|int>
	 */
	private static function newBaseBookingData(): array {
		return [
			'transactionId' => self::PAYMENT_ID,
			'amount' => self::AMOUNT,
			'customerId' => self::CUSTOMER_ID,
			'sessionId' => self::SESSION_ID,
			'auth' => self::AUTH_ID,
			'title' => self::TITLE,
			'country' => self::COUNTRY_CODE,
			'currency' => self::CURRENCY_CODE,
		];
	}

}
