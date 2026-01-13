<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\Backup\DatabaseBackupClient;
use WMDE\Fundraising\DonationContext\DataAccess\Backup\PersonalDataBackup;
use WMDE\Fundraising\DonationContext\DataAccess\Backup\TableBackupConfiguration;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DatabaseBackupClientSpy;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

#[CoversClass( PersonalDataBackup::class )]
class PersonalDataBackupTest extends TestCase {
	private const string BACKUP_TIME = '2025-03-04 0:00:00';

	public function testBackupClientIsCalledWithTableNameAndConditionsForDonors(): void {
		$backupClientSpy = new DatabaseBackupClientSpy();
		$personalBackup = $this->givenPersonalBackup( backupClient: $backupClientSpy );
		// A regular expression to check that the conditions contain a subselect
		$expectedSubSelect = '/select .* where is_scrubbed=0 AND dt_backup IS NULL/i';

		$personalBackup->doBackup( $this->givenBackupTime() );

		$backupConfigurations = $backupClientSpy->getTableBackupConfigurations();
		$this->assertCount( 3, $backupConfigurations );
		$this->assertStringContainsString( 'payment', $backupConfigurations[0]->tableName, 'Should backup payment tables first' );
		$this->assertMatchesRegularExpression( $expectedSubSelect, $backupConfigurations[0]->conditions );
		$this->assertSame( 'donation_tracking', $backupConfigurations[1]->tableName, 'Should backup donation tracking' );
		$this->assertMatchesRegularExpression( $expectedSubSelect, $backupConfigurations[1]->conditions );
		$this->assertEquals(
			new TableBackupConfiguration( 'spenden', 'is_scrubbed=0 AND dt_backup IS NULL' ),
			$backupConfigurations[2]
		);
	}

	public function testDonationsGetMarkedWithBackupDate(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$em = $factory->getEntityManager();
		$this->givenDonations( $em );
		$personalBackup = $this->givenPersonalBackup( entityManager: $em );
		$backupTime = $this->givenBackupTime();

		$personalBackup->doBackup( $backupTime );

		$qb = $em->createQueryBuilder();
		$qb->select( 'COUNT(d) AS updated_donations' )
			->from( Donation::class, 'd' )
			->where( 'd.dtBackup=:backupTime' )
			->setParameter( 'backupTime', $backupTime->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( [ [ 'updated_donations' => 3 ] ], $qb->getQuery()->getScalarResult() );
	}

	public function testDoBackupReturnsNumberOfAffectedItems(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$em = $factory->getEntityManager();
		$this->givenDonations( $em );
		$personalBackup = $this->givenPersonalBackup( entityManager: $em );

		$affectedItems = $personalBackup->doBackup( $this->givenBackupTime() );

		$this->assertSame( 3, $affectedItems );
	}

	private function givenDonations( EntityManager $entityManager ): void {
		// Insert 3 donations without backup marker
		for ( $i = 1; $i <= 3; $i++ ) {
			$donation = ValidDoctrineDonation::newExportedirectDebitDoctrineDonation();
			$donation->setId( $i );
			$entityManager->persist( $donation );
		}

		// Insert 2 donations with backup marker (i.e. they have been backed up previously)
		$backupTime = new \DateTime();
		for ( $i = 4; $i <= 6; $i++ ) {
			$donation = ValidDoctrineDonation::newExportedirectDebitDoctrineDonation();
			$donation->setDtBackup( $backupTime );
			$donation->setId( $i );
			$entityManager->persist( $donation );
			$backupTime->modify( '+1 day' );
		}

		$entityManager->flush();
	}

	private function givenBackupTime(): \DateTimeImmutable {
		return new \DateTimeImmutable( self::BACKUP_TIME );
	}

	private function givenPersonalBackup( ?DatabaseBackupClientSpy $backupClient = null, ?EntityManager $entityManager = null ): PersonalDataBackup {
		$backupClient = $backupClient ?? $this->createStub( DatabaseBackupClient::class );
		if ( $entityManager === null ) {
			$factory = TestEnvironment::newInstance()->getFactory();
			$entityManager = $factory->getEntityManager();
		}
		return new PersonalDataBackup( $backupClient, $entityManager );
	}
}
