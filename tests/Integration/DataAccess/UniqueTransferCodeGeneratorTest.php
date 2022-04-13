<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\DataAccess\ModerationReasonRepository;
use WMDE\Fundraising\DonationContext\DataAccess\UniqueTransferCodeGenerator;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentInterval;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentReferenceCode;
use WMDE\Fundraising\PaymentContext\Domain\PaymentReferenceCodeGenerator;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\UniqueTransferCodeGenerator
 */
class UniqueTransferCodeGeneratorTest extends TestCase {

	private EntityManager $entityManager;

	public function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
	}

	private function newUniqueGenerator(): UniqueTransferCodeGenerator {
		return new UniqueTransferCodeGenerator(
			$this->newFakeGenerator(),
			$this->entityManager
		);
	}

	private function newFakeGenerator(): PaymentReferenceCodeGenerator {
		return new class() extends PaymentReferenceCodeGenerator {
			private $position = 0;

			protected function getNextCharacterIndex(): int {
				return $this->position++;
			}
		};
	}

	public function testWhenFirstResultIsUnique_itGetsReturned(): void {
		$this->markTestIncomplete( 'Test should be rewritten for new payment db structure' );
		// $this->assertSame( 'X-first', $this->newUniqueGenerator()->generateTransferCode( 'X-' ) );
	}

	public function testWhenFirstResultIsNotUnique_secondResultGetsReturned(): void {
		$this->markTestIncomplete( 'Test should be rewritten for new payment db structure' );
		// $this->storeDonationWithTransferCode( 'X-first' );
		//$this->assertSame( 'X-second', $this->newUniqueGenerator()->generateTransferCode( 'X-' ) );
	}

	private function storeDonationWithTransferCode( string $code ): void {
		$donation = new Donation(
			null,
			Donation::STATUS_NEW,
			ValidDonation::newDonor(),
			BankTransferPayment::create( 1, Euro::newFromCents( 100 ), PaymentInterval::OneTime, PaymentReferenceCode::newFromString( $code ) ),
			Donation::OPTS_INTO_NEWSLETTER,
			ValidDonation::newTrackingInfo()
		);

		( new DoctrineDonationRepository( $this->entityManager, new ModerationReasonRepository( $this->entityManager ) ) )->storeDonation( $donation );
	}

	public function testWhenFirstAndSecondResultsAreNotUnique_thirdResultGetsReturned(): void {
		$this->markTestIncomplete( 'Test should be rewritten for new payment db structure' );
		// $this->storeDonationWithTransferCode( 'X-first' );
		//$this->storeDonationWithTransferCode( 'X-second' );
		//$this->assertSame( 'X-third', $this->newUniqueGenerator()->generateTransferCode( 'X-' ) );
	}

}
