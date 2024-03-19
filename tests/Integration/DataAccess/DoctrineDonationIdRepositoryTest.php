<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationIdRepository;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationId;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

#[CoversClass( DonationId::class )]
class DoctrineDonationIdRepositoryTest extends TestCase {

	private EntityManager $entityManager;

	public function setUp(): void {
		$factory = TestEnvironment::newInstance()->getFactory();
		$this->entityManager = $factory->getEntityManager();
	}

	public function testWhenDonationIdTableIsEmpty_throwsException(): void {
		$this->expectException( \RuntimeException::class );

		$this->makeRepository()->getNewId();
	}

	public function testWhenGetNextId_getsNextId(): void {
		$this->whenDonationIdIs( 4 );
		$this->assertEquals( 5, $this->makeRepository()->getNewId() );
	}

	private function makeRepository(): DonationIdRepository {
		return new DoctrineDonationIdRepository( $this->entityManager );
	}

	private function whenDonationIdIs( int $donationId ): void {
		$this->entityManager->persist( new DonationId( $donationId ) );
		$this->entityManager->flush();
	}
}
