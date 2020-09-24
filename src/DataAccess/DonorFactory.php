<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;

class DonorFactory {
	public static function createDonorFromEntity( DoctrineDonation $donation ): Donor {
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
			case 'email':
				return new Donor\EmailDonor(
					new PersonName( $data['vorname'], $data['nachname'], $data['anrede'], $data['titel'] ),
					$donation->getDonorEmail()
				);
			case 'anonym':
				return new AnonymousDonor();
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
}
