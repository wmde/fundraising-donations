<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Tools\Setup;
use WMDE\Fundraising\DonationContext\Authorization\RandomTokenGenerator;
use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineSetupFactory;

/**
 * @licence GNU GPL v2+
 */
class DonationContextFactory {

	protected array $config;
	protected string $environment;
	private bool $addDoctrineSubscribers = true;

	// Singleton instances
	protected ?DoctrineSetupFactory $doctrineSetupFactory;
	protected ?TokenGenerator $tokenGenerator;

	public function __construct( array $config, string $environment = 'dev' ) {
		$this->config = $config;
		$this->environment = $environment;
		$this->doctrineSetupFactory = null;
		$this->tokenGenerator = null;
	}

	public function getDoctrineSetupFactory(): DoctrineSetupFactory {
		if ( is_null( $this->doctrineSetupFactory ) ) {
			$this->doctrineSetupFactory = new DoctrineSetupFactory(
				Setup::createConfiguration(
					$this->isDevEnvironment(),
					$this->getVarPath() . '/doctrine_proxies'
				)
			);
		}
		return $this->doctrineSetupFactory;
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
			$this->getDoctrineSetupFactory()->newEventSubscribers(),
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
		if ( is_null( $this->tokenGenerator ) ) {
			$this->tokenGenerator = new RandomTokenGenerator(
				$this->config['token-length'],
				new \DateInterval( $this->config['token-validity-timestamp'] )
			);
		}
		return $this->tokenGenerator;
	}

	public function disableDoctrineSubscribers(): void {
		$this->addDoctrineSubscribers = false;
	}

	protected function isDevEnvironment(): bool {
		return $this->environment === 'dev' || $this->environment === 'test';
	}

}