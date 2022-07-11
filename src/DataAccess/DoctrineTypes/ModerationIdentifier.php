<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineTypes;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier as DomainModerationIdentifier;

class ModerationIdentifier extends Type {

	public function getSQLDeclaration( array $column, AbstractPlatform $platform ) {
		// we're using a backed enum to map the actual moderation identifier names with integers
		return 'VARCHAR(50)';
	}

	public function getName() {
		return 'DonationModerationIdentifier';
	}

	public function convertToPHPValue( mixed $value, AbstractPlatform $platform ): DomainModerationIdentifier {
		return constant( "WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier::{$value}" );
	}

	public function convertToDatabaseValue( mixed $value, AbstractPlatform $platform ): string {
		if ( !$value instanceof DomainModerationIdentifier ) {
			throw new \InvalidArgumentException( 'Provided value must of the type ' . DomainModerationIdentifier::class );
		}

		return $value->name;
	}
}
