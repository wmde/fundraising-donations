<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\Address;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\NoAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\NoName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;

/**
 * Convert a Donor into an array of fields for the legacy database schema that
 * stores all personal information in a serialized blob.
 */
class DonorFieldMapper {
	public static function getPersonalDataFields( Donor $donor ): array {
		return array_merge(
			// Order of the fields is the same as the resulting array of ValidDoctrineDonation,
			// otherwise comparing the serialized data will fail
			[
				'adresstyp' => self::getAddressType( $donor ),
			],
			self::getDataFieldsFromPersonName( $donor->getName() ),
			self::getDataFieldsFromAddress( $donor->getPhysicalAddress() ),
			$donor instanceof AnonymousDonor ? [] : [ 'email' => $donor->getEmailAddress() ]
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

	private static function getDataFieldsFromAddress( Address $address ): array {
		if ( $address instanceof NoAddress ) {
			return [];
		}
		return [
			'strasse' => $address->getStreetAddress(),
			'plz' => $address->getPostalCode(),
			'ort' => $address->getCity(),
			'country' => $address->getCountryCode(),
		];
	}

	/**
	 * Update donation information if the Donor is not anonymous
	 *
	 * @param DoctrineDonation $doctrineDonation
	 * @param Donor $donor
	 */
	public static function updateDonorInformation( DoctrineDonation $doctrineDonation, Donor $donor ): void {
		if ( $donor instanceof AnonymousDonor ) {
			return;
		}
		$doctrineDonation->setDonorFullName( $donor->getName()->getFullName() );
		$doctrineDonation->setDonorEmail( $donor->getEmailAddress() );

		// protect against email-only updates accidentally overwriting city information
		if ( $donor->getPhysicalAddress() instanceof NoAddress ) {
			return;
		}

		$doctrineDonation->setDonorCity( $donor->getPhysicalAddress()->getCity() );
	}

	private static function getAddressType( Donor $donor ): string {
		$donorTypeMap = [
			Donor\PersonDonor::class => 'person',
			Donor\CompanyDonor::class => 'firma',
			Donor\EmailDonor::class => 'email',
			AnonymousDonor::class => 'anonym'
		];
		$donorNameClass = get_class( $donor );
		if ( empty( $donorTypeMap[$donorNameClass] ) ) {
			throw new \UnexpectedValueException( sprintf( 'Could not determine address type, unexpected donor class "%s"', $donorNameClass ) );
		}
		return $donorTypeMap[$donorNameClass];
	}

}
