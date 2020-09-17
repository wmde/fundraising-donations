<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\PostalAddress;

class DonorFactory {
	public static function createDonorFromEntity( DoctrineDonation $donation ): ?Donor {
		// TODO always return a donor
		if ( !self::entityHasDonorInformation( $donation ) ) {
			return null;
		}

		$data = $donation->getDecodedData();

		switch ( $data['adresstyp'] ) {
			case 'person':
				return new PersonDonor(
					new PersonName( $data['vorname'], $data['nachname'], $data['anrede'], $data['titel'] ),
					self::getPhysicalAddressFromEntity( $donation ),
					$donation->getDonorEmail()
				);
			case 'firma':
				return new CompanyDonor(
					new CompanyName( $data['firma'] ),
					self::getPhysicalAddressFromEntity( $donation ),
					$donation->getDonorEmail()
				);
			default:
				throw new \UnexpectedValueException( sprintf( 'Unknown address type: %s', $data['adresstyp'] ) );
		}
	}

	private static function getPhysicalAddressFromEntity( DoctrineDonation $donation ): PostalAddress {
		$data = $donation->getDecodedData();

		return new PostalAddress(
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
