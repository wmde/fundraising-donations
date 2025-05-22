<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\Backup;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;

/**
 * A class to back up the personal data of Donors (to a short-tem intermediate storage).
 *
 * This class should be called for production data before scrubbing them from the database with
 * {@see \WMDE\Fundraising\DonationContext\Domain\DonationAnonymizer}
 */
class PersonalDataBackup {
	public function __construct(
		private readonly DatabaseBackupClient $backupClient,
		private readonly EntityManager $entityManager
	) {
	}

	public function doBackup( \DateTimeImmutable $backupTime ): int {
		// The backup client will use `mysqldump` internally and may process the result further
		$this->backupClient->backupDonationTables(
			new TableBackupConfiguration( 'spenden', 'is_scrubbed=0 AND dt_backup IS NULL' )
		);

		// Mark affected donations by setting their backup time.
		// Make sure the conditions always match the conditions passed to the backup client!
		$qb = $this->entityManager->createQueryBuilder();
		$qb->update( Donation::class, 'd' )
			->set( 'd.dtBackup', ':backupTime' )
			->where( 'd.isScrubbed=false' )
			->andWhere( 'd.dtBackup IS NULL' )
			->setParameter( 'backupTime', $backupTime );
		/** @var int $affectedRows */
		$affectedRows = $qb->getQuery()->execute();

		// Clear all lingering entities, they don't get changed by the update query
		// See https://www.doctrine-project.org/projects/doctrine-orm/en/3.3/reference/dql-doctrine-query-language.html#update-queries
		$this->entityManager->clear();

		return $affectedRows;
	}
}
