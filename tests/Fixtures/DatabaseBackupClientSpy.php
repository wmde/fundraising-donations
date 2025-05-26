<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\DataAccess\Backup\DatabaseBackupClient;
use WMDE\Fundraising\DonationContext\DataAccess\Backup\TableBackupConfiguration;

class DatabaseBackupClientSpy implements DatabaseBackupClient {
	/**
	 * @var TableBackupConfiguration[]
	 */
	private ?array $tableBackupConfigurations = null;

	public function backupDonationTables( TableBackupConfiguration ...$backupConfigurations ): void {
		if ( $this->tableBackupConfigurations !== null ) {
			throw new \LogicException( "backupTable must only be called once!" );
		}
		$this->tableBackupConfigurations = $backupConfigurations;
	}

	/**
	 * @return TableBackupConfiguration[]
	 */
	public function getTableBackupConfigurations(): array {
		if ( $this->tableBackupConfigurations === null ) {
			throw new \LogicException( 'backupTable was never called!' );
		}
		return $this->tableBackupConfigurations;
	}

}
