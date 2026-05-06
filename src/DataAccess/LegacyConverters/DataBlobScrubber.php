<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;

class DataBlobScrubber {
	private const array PERSONAL_DATA_FIELDS = [
		'vorname',
		'nachname',
		'plz',
		'ort',
		'strasse',
		'email',
		'phone',
		'dob',
		'bankname',
		'konto',
		'blz',
		'bic',
		'iban',
		'titel',
		'paypal_first_name',
		'paypal_last_name',
		'paypal_address_name'
	];

	/**
	 * We keep anrede for measuring gender
	 */
	private const array ALLOWED_DATA_FIELDS = [
		'impCount',
		'bImpCount',
		'tracking',
		'anrede',
		'log',
		'adresstyp'
	];

	/**
	 * This is for cleaning out the donor data from the data blob
	 * apart from the items specified in ALLOWED_DATA_FIELDS
	 *
	 * @param DoctrineDonation $donation
	 *
	 * @return void
	 */
	public static function scrubAllPersonalData( DoctrineDonation $donation ): void {
		$blobData = $donation->getDecodedData();

		$allowedData = [];
		foreach ( self::ALLOWED_DATA_FIELDS as $field ) {
			if ( isset( $blobData[ $field ] ) ) {
				$allowedData[ $field ] = $blobData[ $field ];
			}
		}

		$donation->encodeAndSetData( $allowedData );
	}

	/**
	 * This clears the fields specified in PERSONAL_DATA_FIELDS from the data blob
	 * It is used when an admin converts a moderated donation to anonymous
	 *
	 * @param DoctrineDonation $donation
	 *
	 * @return void
	 */
	public static function makeDonorAnonymous( DoctrineDonation $donation ): void {
		$blobData = $donation->getDecodedData();
		foreach ( self::PERSONAL_DATA_FIELDS as $field ) {
			unset( $blobData[$field] );
		}
		$donation->encodeAndSetData( $blobData );
	}
}
