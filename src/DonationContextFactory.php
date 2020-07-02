<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use DateInterval;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ORM\Configuration;
use Gedmo\Timestampable\TimestampableListener;
use WMDE\Fundraising\DonationContext\Authorization\RandomTokenGenerator;
use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber;

/**
 * @license GPL-2.0-or-later
 */
class DonationContextFactory {

	/**
	 * Use this constant for MappingDriverChain::addDriver
	 */
	public const ENTITY_NAMESPACE = 'WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities';

	private const ENTITY_PATHS = [
		__DIR__ . '/DataAccess/DoctrineEntities/',
	];

	protected array $config;
	protected Configuration $doctrineConfiguration;
	private AnnotationReader $annotationReader;

	// Singleton instances
	protected ?TokenGenerator $tokenGenerator;

	public function __construct( array $config, Configuration $doctrineConfiguration ) {
		$this->config = $config;
		$this->doctrineConfiguration = $doctrineConfiguration;
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

	public function newMappingDriver(): MappingDriver {
		// We're only calling this for the side effect of adding Mapping/Driver/DoctrineAnnotations.php
		// to the AnnotationRegistry. When AnnotationRegistry is deprecated with Doctrine Annotations 2.0,
		// use $this->>annotationReader instead
		return $this->doctrineConfiguration->newDefaultAnnotationDriver( self::ENTITY_PATHS, false );
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

}
