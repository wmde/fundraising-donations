<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Pimple\Container;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber;
use WMDE\Fundraising\Frontend\Infrastructure\RandomTokenGenerator;
use WMDE\Fundraising\Frontend\Infrastructure\TokenGenerator;
use WMDE\Fundraising\Store\Factory as StoreFactory;
use WMDE\Fundraising\Store\Installer;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DonationContextFactory {

	private $config;

	/**
	 * @var Container
	 */
	private $pimple;

	private $addDoctrineSubscribers = true;

	public function __construct( array $config ) {
		$this->config = $config;
		$this->pimple = $this->newPimple();
	}

	private function newPimple(): Container {
		$pimple = new Container();

		$pimple['dbal_connection'] = function() {
			return DriverManager::getConnection( $this->config['db'] );
		};

		$pimple['entity_manager'] = function() {
			$entityManager = ( new StoreFactory( $this->getConnection(), $this->getVarPath() . '/doctrine_proxies' ) )
				->getEntityManager();
			if ( $this->addDoctrineSubscribers ) {
				$entityManager->getEventManager()->addEventSubscriber( $this->newDoctrineDonationPrePersistSubscriber() );
			}

			return $entityManager;
		};

		$pimple['token_generator'] = function() {
			return new RandomTokenGenerator(
				$this->config['token-length'],
				new \DateInterval( $this->config['token-validity-timestamp'] )
			);
		};

		return $pimple;
	}

	public function getConnection(): Connection {
		return $this->pimple['dbal_connection'];
	}

	public function getEntityManager(): EntityManager {
		return $this->pimple['entity_manager'];
	}

	public function newInstaller(): Installer {
		return ( new StoreFactory( $this->getConnection() ) )->newInstaller();
	}

	private function getVarPath(): string {
		return $this->config['var-path'];
	}

	private function newDoctrineDonationPrePersistSubscriber(): DoctrineDonationPrePersistSubscriber {
		$tokenGenerator = $this->getTokenGenerator();
		return new DoctrineDonationPrePersistSubscriber(
			$tokenGenerator,
			$tokenGenerator
		);
	}

	public function getTokenGenerator(): TokenGenerator {
		return $this->pimple['token_generator'];
	}

	public function disableDoctrineSubscribers(): void {
		$this->addDoctrineSubscribers = false;
	}

}