<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;

class DonationContextFactory {

	private const DOCTRINE_CLASS_MAPPING_DIRECTORY = __DIR__ . '/../config/DoctrineClassMapping';

	/**
	 * @return string[]
	 */
	public function getDoctrineMappingPaths(): array {
		return [ self::DOCTRINE_CLASS_MAPPING_DIRECTORY ];
	}

	public function registerCustomTypes( Connection $connection ): void {
		$this->registerDoctrineModerationIdentifierType( $connection );
	}

	public function registerDoctrineModerationIdentifierType( Connection $connection ): void {
		static $isRegistered = false;
		if ( $isRegistered ) {
			return;
		}
		Type::addType( 'DonationModerationIdentifier', 'WMDE\Fundraising\DonationContext\DataAccess\DoctrineTypes\ModerationIdentifier' );
		$connection->getDatabasePlatform()->registerDoctrineTypeMapping( 'DonationModerationIdentifier', 'DonationModerationIdentifier' );
		$isRegistered = true;
	}

}
