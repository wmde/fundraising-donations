<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonorAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\LegacyDonorName;

class DonorFactory {
	public static function createDonorFromEntity( DoctrineDonation $donation ): ?Donor {
		// TODO always return a donor
		if ( !self::entityHasDonorInformation( $donation ) ) {
			return null;
		}

		return new LegacyDonor(
			self::getPersonNameFromEntity( $donation ),
			self::getPhysicalAddressFromEntity( $donation ),
			$donation->getDonorEmail()
		);
	}

	private static function getPersonNameFromEntity( DoctrineDonation $donation ): LegacyDonorName {
		$data = $donation->getDecodedData();

		// TODO cater to more address types
		$name = $data['adresstyp'] === LegacyDonorName::PERSON_COMPANY
			? LegacyDonorName::newCompanyName() : LegacyDonorName::newPrivatePersonName();

		$name->setSalutation( $data['anrede'] );
		$name->setTitle( $data['titel'] );
		$name->setFirstName( $data['vorname'] );
		$name->setLastName( $data['nachname'] );
		$name->setCompanyName( $data['firma'] );

		return $name->freeze()->assertNoNullFields();
	}

	private static function getPhysicalAddressFromEntity( DoctrineDonation $ddonation ): LegacyDonorAddress {
		$data = $ddonation->getDecodedData();

		$address = new LegacyDonorAddress();

		$address->setStreetAddress( $data['strasse'] );
		$address->setCity( $data['ort'] );
		$address->setPostalCode( $data['plz'] );
		$address->setCountryCode( $data['country'] );

		return $address->freeze()->assertNoNullFields();
	}

	private static function entityHasDonorInformation( DoctrineDonation $dd ): bool {
		// If entity was backed up, its information was purged
		if ( $dd->getDtBackup() !== null ) {
			return false;
		}

		$data = $dd->getDecodedData();

		return isset( $data['adresstyp'] ) && $data['adresstyp'] !== LegacyDonorName::PERSON_ANONYMOUS;
	}
}
