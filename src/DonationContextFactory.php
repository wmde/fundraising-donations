<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;

/**
 * @license GPL-2.0-or-later
 */
class DonationContextFactory {

	private const DOCTRINE_CLASS_MAPPING_DIRECTORY = __DIR__ . '/../config/DoctrineClassMapping';

	/**
	 * @var array{token-length:int,token-validity-timestamp:string}
	 */
	protected array $config;

	/**
	 * @param array{token-length:int,token-validity-timestamp:string} $config
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

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
