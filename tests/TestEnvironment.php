<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class TestEnvironment {

	private $config;
	private $factory;

	public static function newInstance(): self {
		$environment = new self(
			[
				'db' => [
					'driver' => 'pdo_sqlite',
					'memory' => true,
				],
				'var-path' => '/tmp',
				'token-length' => 16,
				'token-validity-timestamp' => 'PT4H',
			]
		);

		$environment->install();

		return $environment;
	}

	private function __construct( array $config ) {
		$this->config = $config;
		$this->factory = new TestDonationContextFactory( $this->config );
	}

	private function install(): void {
		$schemaCreator = $this->getFactory()->newSchemaCreator();

		try {
			$schemaCreator->dropSchema();
		}
		catch ( \Exception $ex ) {
		}

		$schemaCreator->createSchema();
	}

	public function getFactory(): TestDonationContextFactory {
		return $this->factory;
	}

}