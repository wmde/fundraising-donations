<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\ScrubbedDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

class DonorFactory {
	public static function createDonorFromEntity( DoctrineDonation $donation ): Donor {
		$data = new DataReaderWithDefault( $donation->getDecodedData() );
		$rawAddressType = $data->getValue( 'adresstyp' );

		if ( $donation->isScrubbed() ) {
			$donorType = self::createDonorTypeFromRawAddressType( $rawAddressType );
			return new ScrubbedDonor(
				new Donor\Name\ScrubbedName( $data->getValue( 'anrede' ) ),
				$donorType,
				$donation->getDonorOptsIntoNewsletter(),
				(bool)$donation->getDonationReceipt()
			);
		}

		switch ( $rawAddressType ) {
			case 'person':
				return new PersonDonor(
					new PersonName(
						$data->getValue( 'vorname' ),
						$data->getValue( 'nachname' ),
						$data->getValue( 'anrede' ),
						$data->getValue( 'titel' )
					),
					self::createPhysicalAddress( $data ),
					$donation->getDonorEmail() ?? ''
				);
			case 'firma':
				return new CompanyDonor(
					new CompanyContactName(
						$data->getValue( 'firma' ),
						$data->getValue( 'vorname' ),
						$data->getValue( 'nachname' ),
						$data->getValue( 'anrede' ),
						$data->getValue( 'titel' )
					),
					self::createPhysicalAddress( $data ),
					$donation->getDonorEmail() ?? ''
				);
			case 'email':
				return new Donor\EmailDonor(
					new PersonName(
						$data->getValue( 'vorname' ),
						$data->getValue( 'nachname' ),
						$data->getValue( 'anrede' ),
						$data->getValue( 'titel' )
					),
					$donation->getDonorEmail() ?? ''
				);
			case 'anonym':
				return new AnonymousDonor();
			default:
				throw new \UnexpectedValueException( sprintf( 'Unknown address type: %s', $data->getValue( 'adresstyp' ) ) );
		}
	}

	private static function createPhysicalAddress( DataReaderWithDefault $data ): PostalAddress {
		return new PostalAddress(
			$data->getValue( 'strasse' ),
			$data->getValue( 'plz' ),
			$data->getValue( 'ort' ),
			$data->getValue( 'country' )
		);
	}

	private static function createDonorTypeFromRawAddressType( string $addressType ): DonorType {
		return match ( $addressType ) {
			'firma' => DonorType::COMPANY,
			'email' => DonorType::EMAIL,
			'person' => DonorType::PERSON,
			'anonym' => DonorType::ANONYMOUS,
			default => throw new \InvalidArgumentException( sprintf( 'Unknown donor type: %s', $addressType ) ),
		};
	}
}
