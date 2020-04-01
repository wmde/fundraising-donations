<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Tools\Setup;
use Gedmo\Timestampable\TimestampableListener;

class DoctrineSetupFactory {

	/**
	 * Use this constant for MappingDriverChain::addDriver
	 */
	public const ENTITY_NAMESPACE = 'WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities';

	private const ENTITY_PATHS = [
		__DIR__ . '/DoctrineEntities/',
		__DIR__ . '/DoctrineEntities/DonationPayments/'
	];

	private AnnotationReader $annotationReader;
	private Configuration $config;

	public function __construct( ?Configuration $config = null ) {
		if ( $config === null ) {
			$config = Setup::createConfiguration();
		}
		$this->config = $config;
		$this->annotationReader = new AnnotationReader();
	}

	public function newMappingDriver(): MappingDriver {
		// We're only calling this for the side effect of adding Mapping/Driver/DoctrineAnnotations.php
		// to the AnnotationRegistry. When AnnotationRegistry is deprecated with Doctrine Annotations 2.0,
		// instantiate AnnotationReader directly instead.
		return $this->config->newDefaultAnnotationDriver( self::ENTITY_PATHS, false );
	}

	/**
	 * @return EventSubscriber[]
	 */
	public function newEventSubscribers(): array {
		$timestampableListener = new TimestampableListener;
		$timestampableListener->setAnnotationReader( $this->annotationReader );
		return [
			TimestampableListener::class => $timestampableListener
		];
	}



}