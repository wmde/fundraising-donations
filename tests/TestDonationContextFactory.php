<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Gedmo\Timestampable\TimestampableListener;
use WMDE\Fundraising\DonationContext\DonationContextFactory;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedTokenGenerator;

class TestDonationContextFactory {

	private Configuration $doctrineConfig;
	private DonationContextFactory $contextFactory;
	private array $config;

	private ?EntityManager $entityManager;
	private ?Connection $connection;

	public function __construct( array $config ) {
		$this->config = $config;
		$this->doctrineConfig = Setup::createConfiguration( true );
		$this->contextFactory = new DonationContextFactory(
			$config,
			$this->doctrineConfig
		);
		$this->contextFactory->setTokenGenerator( new FixedTokenGenerator() );
		$this->entityManager = null;
		$this->connection = null;
	}

	public function getConnection(): Connection {
		if ( $this->connection === null ) {
			$this->connection = DriverManager::getConnection( $this->config['db'] );
			$this->contextFactory->registerCustomTypes( $this->connection );
		}
		return $this->connection;
	}

	public function getEntityManager(): EntityManager {
		if ( $this->entityManager === null ) {
			$this->entityManager = $this->newEntityManager( $this->contextFactory->newEventSubscribers() );
		}
		return $this->entityManager;
	}

	private function newEntityManager( array $eventSubscribers = [] ): EntityManager {
		AnnotationRegistry::registerLoader( 'class_exists' );
		$this->doctrineConfig->setMetadataDriverImpl( $this->contextFactory->newMappingDriver() );

		$entityManager = EntityManager::create( $this->getConnection(), $this->doctrineConfig );

		$this->setupEventSubscribers( $entityManager->getEventManager(), $eventSubscribers );

		return $entityManager;
	}

	private function setupEventSubscribers( EventManager $eventManager, array $eventSubscribers ): void {
		foreach ( $eventSubscribers as $eventSubscriber ) {
			$eventManager->addEventSubscriber( $eventSubscriber );
		}
	}

	public function newSchemaCreator(): SchemaCreator {
		return new SchemaCreator( $this->newEntityManager( [
			TimestampableListener::class => $this->newTimestampableListener()
		] ) );
	}

	private function newTimestampableListener(): TimestampableListener {
		$timestampableListener = new TimestampableListener;
		$timestampableListener->setAnnotationReader( new AnnotationReader() );
		return $timestampableListener;
	}

}
