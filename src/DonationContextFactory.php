<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Setup;
use Pimple\Container;
use WMDE\Fundraising\DonationContext\Authorization\RandomTokenGenerator;
use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineSetupFactory;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DonationContextFactory {

	protected array $config;
	protected string $environment;

	/**
	 * @var Container
	 */
	private $pimple;

	private $addDoctrineSubscribers = true;

	public function __construct( array $config, string $environment = 'dev' ) {
		$this->config = $config;
		$this->pimple = $this->newPimple();
		$this->environment = $environment;
	}

	private function newPimple(): Container {
		$pimple = new Container();

		$pimple['entity_manager_factory'] = function () {
			return new DoctrineSetupFactory(
				Setup::createConfiguration( $this->isDevEnvironment(), $this->getVarPath() . '/doctrine_proxies' )
			);
		};

		$pimple['token_generator'] = function() {
			return new RandomTokenGenerator(
				$this->config['token-length'],
				new \DateInterval( $this->config['token-validity-timestamp'] )
			);
		};

		return $pimple;
	}

	private function getVarPath(): string {
		return $this->config['var-path'];
	}

	/**
	 * @return EventSubscriber[]
	 */
	public function newDoctrineEventSubscribers(): array {
		if ( !$this->addDoctrineSubscribers ) {
			return [];
		}
		return array_merge(
			$this->getEntityManagerFactory()->newEventSubscribers(),
			[
				DoctrineDonationPrePersistSubscriber::class => $this->newDoctrineDonationPrePersistSubscriber()
			]
		);
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

	public function getEntityManagerFactory(): DoctrineSetupFactory {
		return $this->pimple['entity_manager_factory'];
	}

	protected function isDevEnvironment(): bool {
		return $this->environment === 'dev' || $this->environment === 'test';
	}

}