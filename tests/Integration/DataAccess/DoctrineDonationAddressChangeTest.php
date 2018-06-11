<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;
use WMDE\Fundraising\Entities\Donation;

/**
 * @covers WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationPrePersistSubscriber
 */
class DoctrineDonationAddressChangeTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
	}

	/**
	 * @slowThreshold 400
	 */
	public function testWhenDonationIsCreated_addressChangeUuidIsStored(): void {
		$donation = new Donation();
		$this->assertNull( $donation->getAddressChange() );
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();

		/** @var Donation $persistedDonation */
		$persistedDonation = $this->entityManager->find( Donation::class, 1 );
		$this->assertSame(
			$donation->getAddressChange()->getCurrentIdentifier(),
			$persistedDonation->getAddressChange()->getCurrentIdentifier()
		);
	}

	/**
	 * @slowThreshold 400
	 */
	public function testWhenAddressIsUpdated_addressChangeUuidIsUpdated(): void {
		$donation = new Donation();

		$this->entityManager->persist( $donation );
		$this->entityManager->flush();

		$oldId = $donation->getAddressChange()->getCurrentIdentifier();

		/** @var Donation $persistedDonation */
		$persistedDonation = $this->entityManager->find( Donation::class, 1 );
		$persistedDonation->getAddressChange()->updateAddressIdentifier();

		$this->assertNotSame( $oldId, $persistedDonation->getAddressChange()->getCurrentIdentifier() );
		$this->assertSame( $oldId, $persistedDonation->getAddressChange()->getPreviousIdentifier() );
	}
}
