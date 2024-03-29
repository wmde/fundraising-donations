<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineTypes;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier as DomainModerationIdentifier;

class ModerationIdentifier extends Type {

	public function getSQLDeclaration( array $column, AbstractPlatform $platform ): string {
		// we're using a backed enum to map the actual moderation identifier names with integers
		return 'VARCHAR(50)';
	}

	public function getName(): string {
		return 'DonationModerationIdentifier';
	}

	public function convertToPHPValue( mixed $value, AbstractPlatform $platform ): DomainModerationIdentifier {
		if ( !is_string( $value ) ) {
			throw new InvalidArgumentException( "Invalid value provided for ModerationIdentifier" );
		}

		return DomainModerationIdentifier::from( $value );
	}

	public function convertToDatabaseValue( mixed $value, AbstractPlatform $platform ): string {
		if ( !$value instanceof DomainModerationIdentifier ) {
			throw new \InvalidArgumentException( 'Provided value must of the type ' . DomainModerationIdentifier::class );
		}

		return $value->name;
	}
}
