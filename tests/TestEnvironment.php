<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests;

class TestEnvironment {

	private array $config;
	private TestDonationContextFactory $factory;

	public static function newInstance(): self {
		$environment = new self();

		$environment->install();

		return $environment;
	}

	private function __construct() {
		$config = [
			'db' => [
				'driver' => 'pdo_sqlite',
				'memory' => true,
			],
			'var-path' => '/tmp',
			'token-length' => 16,
			'token-validity-timestamp' => 'PT4H',
		];
		$this->config = $config;
		$this->factory = new TestDonationContextFactory( $this->config );
	}

	private function install(): void {
		$schemaCreator = $this->getFactory()->newSchemaCreator();

		try {
			$schemaCreator->dropSchema();
		} catch ( \Exception $ex ) {
		}

		$schemaCreator->createSchema();
	}

	public function getFactory(): TestDonationContextFactory {
		return $this->factory;
	}

}
