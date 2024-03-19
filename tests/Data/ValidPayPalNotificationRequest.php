<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Data;

use WMDE\Fundraising\DonationContext\UseCases\NotificationRequest;

class ValidPayPalNotificationRequest {

	public const DATA_SET_ID = 12345;
	public const TRANSACTION_ID = '61E67681CH3238416';
	public const PAYER_ID = 'LPLWNMTBWMFAY';
	public const SUBSCRIBER_ID = '8RHHUM3W3PRH7QY6B59';
	public const PAYER_STATUS = 'verified';
	public const PAYER_FIRST_NAME = 'Generous';
	public const PAYER_LAST_NAME = 'Donor';
	public const PAYER_ADDRESS_NAME = 'Generous Donor';
	public const PAYER_ADDRESS_STATUS = 'confirmed';
	public const CURRENCY_CODE = 'EUR';
	public const TRANSACTION_FEE_EURO_STRING = '2.70';
	public const AMOUNT_GROSS_CENTS = 500;
	public const SETTLE_AMOUNT_CENTS = 123;
	public const PAYMENT_TIMESTAMP = '20:12:59 Jan 13, 2009 PST';
	public const PAYMENT_TYPE = 'instant';

	public const PAYMENT_STATUS_COMPLETED = 'Completed';
	public const PAYMENT_STATUS_PENDING = 'Pending';

	public static function newInstantPayment( int $donationId ): NotificationRequest {
		return new NotificationRequest(
			array_merge( self::getBaseTransactionData(), [
				'txn_type' => 'express_checkout',
				'ext_payment_status' => self::PAYMENT_STATUS_COMPLETED
			] ),
			$donationId
		);
	}

	public static function newDuplicatePayment( int $donationId, string $transactionid ): NotificationRequest {
		return new NotificationRequest(
			array_merge( self::getBaseTransactionData(), [
				'txn_type' => 'express_checkout',
				'txn_id' => $transactionid,
				'ext_payment_status' => self::PAYMENT_STATUS_COMPLETED,
			] ),
			$donationId
		);
	}

	public static function newPendingPayment(): NotificationRequest {
		return new NotificationRequest(
			array_merge( self::getBaseTransactionData(), [
				'txn_type' => 'express_checkout',
				'ext_payment_status' => self::PAYMENT_STATUS_PENDING
			] ),
			self::DATA_SET_ID
		);
	}

	public static function newSubscriptionModification(): NotificationRequest {
		return new NotificationRequest(
			array_merge( self::getBaseTransactionData(), [
				'txn_type' => 'subscr_modify',
				'ext_payment_status' => self::PAYMENT_STATUS_COMPLETED
			] ),
			self::DATA_SET_ID
		);
	}

	public static function newRecurringPayment( int $donationId ): NotificationRequest {
		return new NotificationRequest(
			array_merge( self::getBaseTransactionData(), [
				'txn_type' => 'subscr_payment',
				'ext_payment_status' => self::PAYMENT_STATUS_COMPLETED
			] ),
			$donationId
		);
	}

	/**
	 * @return array<string,string|int>
	 */
	private static function getBaseTransactionData(): array {
		return [
			'paypal_payer_id' => self::PAYER_ID,
			'paypal_subscr_id' => self::SUBSCRIBER_ID,
			'paypal_payer_status' => self::PAYER_STATUS,
			'paypal_mc_gross' => self::AMOUNT_GROSS_CENTS,
			'paypal_mc_currency' => self::CURRENCY_CODE,
			'paypal_mc_fee' => self::TRANSACTION_FEE_EURO_STRING,
			'paypal_settle_amount' => self::SETTLE_AMOUNT_CENTS,
			'ext_payment_id' => self::TRANSACTION_ID,
			'ext_subscr_id' => self::SUBSCRIBER_ID,
			'ext_payment_type' => self::PAYMENT_TYPE,
			'ext_payment_status' => self::PAYMENT_STATUS_COMPLETED,
			'ext_payment_account' => self::PAYER_ID,
			'ext_payment_timestamp' => self::PAYMENT_TIMESTAMP,
		];
	}

}
