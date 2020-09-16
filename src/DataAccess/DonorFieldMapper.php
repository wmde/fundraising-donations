<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonorAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonorName;

/**
 * Convert a Donor into an array of fields for the legacy database schema that
 * stores all personal information in a serialized blob.
 */
class DonorFieldMapper {
	public static function getPersonalDataFields( ?Donor $donor ): array {
		// TODO make it non-nullable, useDonorType map instead
		if ( $donor === null ) {
			return [ 'adresstyp' => 'anonym' ];
		}

		return array_merge(
			self::getDataFieldsFromPersonName( $donor->getName() ),
			self::getDataFieldsFromAddress( $donor->getPhysicalAddress() ),
			[ 'email' => $donor->getEmailAddress() ]
		);
	}

	private static function getDataFieldsFromPersonName( LegacyDonorName $name ): array {
		// TODO check for null name
		return [
			'adresstyp' => $name->getPersonType(),
			'anrede' => $name->getSalutation(),
			'titel' => $name->getTitle(),
			'vorname' => $name->getFirstName(),
			'nachname' => $name->getLastName(),
			'firma' => $name->getCompanyName(),
		];
	}

	private static function getDataFieldsFromAddress( LegacyDonorAddress $address ): array {
		// TODO check for null address
		return [
			'strasse' => $address->getStreetAddress(),
			'plz' => $address->getPostalCode(),
			'ort' => $address->getCity(),
			'country' => $address->getCountryCode(),
		];
	}

	public static function updateDonorInformation( DoctrineDonation $doctrineDonation, LegacyDonor $donor = null ): void {
		// TODO remove this check when we have an anonymous donor
		if ( $donor === null ) {
			if ( $doctrineDonation->getId() === null ) {
				$doctrineDonation->setDonorFullName( 'Anonym' );
			}
		} else {
			// TODO set when city is available (not anon/email-only)
			$doctrineDonation->setDonorCity( $donor->getPhysicalAddress()->getCity() );
			// TODO set when email is available (not anon)
			$doctrineDonation->setDonorEmail( $donor->getEmailAddress() );
			// TODO alway set
			$doctrineDonation->setDonorFullName( $donor->getName()->getFullName() );
		}
	}

}
