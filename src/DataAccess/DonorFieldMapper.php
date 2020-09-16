<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonorAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\NoName;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonName;

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
			// Order of the fields is the same as the resulting array of ValidDoctrineDonation,
			// otherwise comparing the serialized data will fail
			[
				'adresstyp' => self::getAddressType( $donor ),
			],
			self::getDataFieldsFromPersonName( $donor->getName() ),
			self::getDataFieldsFromAddress( $donor->getPhysicalAddress() ),
			[
				'email' => $donor->getEmailAddress()
			]
		);
	}

	private static function getDataFieldsFromPersonName( DonorName $name ): array {
		$keyToDbFieldMap = [
			'salutation' => 'anrede',
			'title' => 'titel',
			'firstName' => 'vorname',
			'lastName' => 'nachname',
			'companyName' => 'firma',
		];
		$result = [];
		foreach ( $name->toArray() as $k => $v ) {
			if ( empty( $keyToDbFieldMap[$k] ) ) {
				throw new \UnexpectedValueException( sprintf( 'Name class returned unexpected value with key %s', $k ) );
			}
			$result[$keyToDbFieldMap[$k]] = $v;
		}
		return $result;
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

	private static function getAddressType( Donor $donor ): string {
		// TODO use donor type instead of name
		$donorTypeMap = [
			PersonName::class => 'person',
			CompanyName::class => 'firma',
			NoName::class => 'anonym'
		];
		$donorNameClass = get_class( $donor->getName() );
		if ( empty( $donorTypeMap[$donorNameClass] ) ) {
			throw new \UnexpectedValueException( sprintf( 'Could not determine address type, unexpected donor name class "%s"', $donorNameClass ) );
		}
		return $donorTypeMap[$donorNameClass];
	}

}
