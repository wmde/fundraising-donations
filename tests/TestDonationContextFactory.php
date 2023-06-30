<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests;

use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use WMDE\Fundraising\DonationContext\DonationContextFactory;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FixedTokenGenerator;
use WMDE\Fundraising\PaymentContext\PaymentContextFactory;

/**
 * @phpstan-import-type Params from DriverManager
 */
class TestDonationContextFactory {

	private DonationContextFactory $contextFactory;
	/**
	 * @var array{token-length:int,token-validity-timestamp:string,db:Params}
	 */
	private array $config;

	private ?EntityManager $entityManager;
	private ?Connection $connection;

	/**
	 * @param array{token-length:int,token-validity-timestamp:string,db:Params} $config
	 */
	public function __construct( array $config ) {
		$this->config = $config;
		$this->contextFactory = new DonationContextFactory(
			$config,
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

	/**
	 * @param array<EventSubscriber> $eventSubscribers
	 *
	 * @return EntityManager
	 * @throws \Doctrine\ORM\Exception\ORMException
	 */
	private function newEntityManager( array $eventSubscribers = [] ): EntityManager {
		$conn = $this->getConnection();
		$paymentContext = new PaymentContextFactory();
		$paymentContext->registerCustomTypes( $conn );
		$paths = array_merge( $this->contextFactory->getDoctrineMappingPaths(), $paymentContext->getDoctrineMappingPaths() );
		$entityManager = EntityManager::create(
			$conn,
			ORMSetup::createXMLMetadataConfiguration( $paths )
		);

		$this->setupEventSubscribers( $entityManager->getEventManager(), $eventSubscribers );

		return $entityManager;
	}

	/**
	 * @param EventManager $eventManager
	 * @param array<EventSubscriber> $eventSubscribers
	 *
	 * @return void
	 */
	private function setupEventSubscribers( EventManager $eventManager, array $eventSubscribers ): void {
		foreach ( $eventSubscribers as $eventSubscriber ) {
			$eventManager->addEventSubscriber( $eventSubscriber );
		}
	}

	public function newSchemaCreator(): SchemaCreator {
		return new SchemaCreator( $this->newEntityManager() );
	}

}
