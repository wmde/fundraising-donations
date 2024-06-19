<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;

class DataBlobScrubber {
	private const PERSONAL_DATA_FIELDS = [
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

	public static function scrubPersonalDataFromDataBlob( DoctrineDonation $donation ): DoctrineDonation {
		$blobData = $donation->getDecodedData();
		foreach ( self::PERSONAL_DATA_FIELDS as $field ) {
			unset( $blobData[$field] );
		}
		$donation->encodeAndSetData( $blobData );
		return $donation;
	}

}
