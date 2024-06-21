<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMSetup;
use WMDE\Fundraising\DonationContext\DonationContextFactory;
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
		$this->contextFactory = new DonationContextFactory();
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
			$this->entityManager = $this->newEntityManager();
		}
		return $this->entityManager;
	}

	/**
	 * @return EntityManager
	 * @throws ORMException
	 */
	private function newEntityManager(): EntityManager {
		$conn = $this->getConnection();
		$paymentContext = new PaymentContextFactory();
		$paymentContext->registerCustomTypes( $conn );
		$paths = array_merge( $this->contextFactory->getDoctrineMappingPaths(), $paymentContext->getDoctrineMappingPaths() );
		return new EntityManager(
			$conn,
			ORMSetup::createXMLMetadataConfiguration( $paths )
		);
	}

	public function newSchemaCreator(): SchemaCreator {
		return new SchemaCreator( $this->newEntityManager() );
	}

}
