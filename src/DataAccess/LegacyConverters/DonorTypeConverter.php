<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters;

use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

/**
 * This class translates between the Domain class {@see DonorType} and the "adresstyp" key in the "data blob" of
 * the Doctrine {@see \WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation} entity.
 */
class DonorTypeConverter {
	public static function getLegacyDonorType( DonorType $donorType ): string {
		return match ( $donorType ) {
			DonorType::PERSON => 'person',
			DonorType::COMPANY => 'firma',
			DonorType::EMAIL => 'email',
			DonorType::ANONYMOUS => 'anonym'
		};
	}

	public static function getDonorTypeFromString( string $donorType ): DonorType {
		return match ( $donorType ) {
			'firma' => DonorType::COMPANY,
			'email' => DonorType::EMAIL,
			'person' => DonorType::PERSON,
			'anonym' => DonorType::ANONYMOUS,
			default => throw new \InvalidArgumentException( sprintf( 'Unknown donor type: %s', $donorType ) ),
		};
	}
}
