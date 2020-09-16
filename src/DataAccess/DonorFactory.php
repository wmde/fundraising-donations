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

	private static function getPersonNameFromEntity( DoctrineDonation $donation ): DonorName {
		$data = $donation->getDecodedData();

		switch ( $data['adresstyp'] ) {
			case 'person':
				return new PersonName( $data['vorname'], $data['nachname'], $data['anrede'], $data['titel'] );
			case 'firma':
				return new CompanyName( $data['firma'] );
			default:
				return new NoName();
		}
	}

	private static function getPhysicalAddressFromEntity( DoctrineDonation $donation ): LegacyDonorAddress {
		$data = $donation->getDecodedData();

		return new LegacyDonorAddress(
			$data['strasse'],
			$data['plz'],
			$data['ort'],
			$data['country']
		);
	}

	private static function entityHasDonorInformation( DoctrineDonation $dd ): bool {
		// If entity was backed up, its information was purged
		if ( $dd->getDtBackup() !== null ) {
			return false;
		}

		$data = $dd->getDecodedData();

		return isset( $data['adresstyp'] ) && $data['adresstyp'] !== 'anonym';
	}
}
