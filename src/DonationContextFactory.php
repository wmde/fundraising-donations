<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use DateInterval;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use WMDE\Fundraising\DonationContext\Authorization\RandomTokenGenerator;
use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber;

/**
 * @license GPL-2.0-or-later
 */
class DonationContextFactory {

	private const DOCTRINE_CLASS_MAPPING_DIRECTORY = __DIR__ . '/../config/DoctrineClassMapping';

	/**
	 * @var array{token-length:int,token-validity-timestamp:string}
	 */
	protected array $config;

	protected ?TokenGenerator $tokenGenerator;

	/**
	 * @param array{token-length:int,token-validity-timestamp:string} $config
	 */
	public function __construct( array $config ) {
		$this->config = $config;
		$this->tokenGenerator = null;
	}

	/**
	 * @return EventSubscriber[]
	 * @deprecated Use we don't want to use Doctrine event subscribers any more.
	 */
	public function newEventSubscribers(): array {
		return [
			DoctrineDonationPrePersistSubscriber::class => $this->newDoctrineDonationPrePersistSubscriber()
		];
	}

	/**
	 * @return string[]
	 */
	public function getDoctrineMappingPaths(): array {
		return [ self::DOCTRINE_CLASS_MAPPING_DIRECTORY ];
	}

	/**
	 * @deprecated Use we don't want to use Doctrine event subscribers any more.
	 */
	private function newDoctrineDonationPrePersistSubscriber(): DoctrineDonationPrePersistSubscriber {
		$tokenGenerator = $this->getTokenGenerator();
		return new DoctrineDonationPrePersistSubscriber(
			$tokenGenerator,
			$tokenGenerator
		);
	}

	private function getTokenGenerator(): TokenGenerator {
		if ( $this->tokenGenerator === null ) {
			$this->tokenGenerator = new RandomTokenGenerator(
				$this->config['token-length'],
				new DateInterval( $this->config['token-validity-timestamp'] )
			);
		}
		return $this->tokenGenerator;
	}

	/**
	 * Should only be called in tests for switching out the default implementation
	 *
	 * @param TokenGenerator|null $tokenGenerator
	 */
	public function setTokenGenerator( ?TokenGenerator $tokenGenerator ): void {
		$this->tokenGenerator = $tokenGenerator;
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
