<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification\CreditCardPaymentNotificationRequest;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class ValidCreditCardNotificationRequest {

	public const AMOUNT = 13.37;
	public const PAYMENT_ID = 'customer.prefix-ID2tbnag4a9u';
	public const CUSTOMER_ID = 'e20fb9d5281c1bca1901c19f6e46213191bb4c17';
	public const SESSION_ID = 'CC13064b2620f4028b7d340e3449676213336a4d';
	public const AUTH_ID = 'd1d6fae40cf96af52477a9e521558ab7';
	public const TOKEN = 'my_secret_token';
	public const UPDATE_TOKEN = 'my_secret_update_token';
	public const TITLE = 'Your generous donation';
	public const COUNTRY_CODE = 'DE';
	public const CURRENCY_CODE = 'EUR';

	public static function newBillingNotification( int $donationId ): CreditCardPaymentNotificationRequest {
		return self::newBaseRequest()
			->setDonationId( $donationId )
			->setNotificationType( CreditCardPaymentNotificationRequest::NOTIFICATION_TYPE_BILLING );
	}

	private static function newBaseRequest(): CreditCardPaymentNotificationRequest {
		return ( new CreditCardPaymentNotificationRequest() )
			->setTransactionId( self::PAYMENT_ID )
			->setAmount( Euro::newFromFloat( self::AMOUNT ) )
			->setCustomerId( self::CUSTOMER_ID )
			->setSessionId( self::SESSION_ID )
			->setAuthId( self::AUTH_ID )
			->setToken( self::TOKEN )
			->setUpdateToken( self::UPDATE_TOKEN )
			->setTitle( self::TITLE )
			->setCountry( self::COUNTRY_CODE )
			->setCurrency( self::CURRENCY_CODE );
	}

}
