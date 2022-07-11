<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use DateInterval;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Gedmo\Timestampable\TimestampableListener;
use WMDE\Fundraising\DonationContext\Authorization\RandomTokenGenerator;
use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber;

/**
 * @license GPL-2.0-or-later
 */
class DonationContextFactory {

	private const DOCTRINE_CLASS_MAPPING_DIRECTORY = __DIR__ . '/../config/DoctrineClassMapping';

	protected array $config;
	private AnnotationReader $annotationReader;

	protected ?TokenGenerator $tokenGenerator;

	public function __construct( array $config ) {
		$this->config = $config;
		$this->tokenGenerator = null;
		$this->annotationReader = new AnnotationReader();
	}

	/**
	 * @return EventSubscriber[]
	 */
	public function newEventSubscribers(): array {
		return array_merge(
			[
				TimestampableListener::class => $this->newTimestampableListener(),
				DoctrineDonationPrePersistSubscriber::class => $this->newDoctrineDonationPrePersistSubscriber()
			]
		);
	}

	public function getDoctrineMappingPaths(): array {
		return [ self::DOCTRINE_CLASS_MAPPING_DIRECTORY ];
	}

	private function newTimestampableListener(): TimestampableListener {
		$timestampableListener = new TimestampableListener;
		$timestampableListener->setAnnotationReader( $this->annotationReader );
		return $timestampableListener;
	}

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
