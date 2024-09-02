<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\ScrubbedName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\ScrubbedDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;

#[CoversClass( ScrubbedDonor::class )]
class ScrubbedDonorTest extends TestCase {

	public function testScrubbedDonorSupportsType(): void {
		$personDonor = new ScrubbedDonor( new ScrubbedName( 'Herr' ), DonorType::PERSON, false, false );
		$companyDonor = new ScrubbedDonor( new ScrubbedName( 'Firma' ), DonorType::COMPANY, false, false );
		$this->assertSame( DonorType::PERSON, $personDonor->getDonorType() );
		$this->assertSame( DonorType::COMPANY, $companyDonor->getDonorType() );
	}

	public function testScrubbedDonorSupportsName(): void {
		$donor = new ScrubbedDonor( new ScrubbedName( 'Frau' ), DonorType::PERSON, false, false );
		$this->assertSame( 'Frau', $donor->getName()->getSalutation() );
	}

	public function testScrubbedDonorSupportsMailingListSubscription(): void {
		$subscribedDonor = new ScrubbedDonor( new ScrubbedName( 'Herr' ), DonorType::PERSON, true, false );
		$unsubscribedDonor = new ScrubbedDonor( new ScrubbedName( 'Herr' ), DonorType::PERSON, false, false );
		$this->assertTrue( $subscribedDonor->isSubscribedToMailingList() );
		$this->assertFalse( $unsubscribedDonor->isSubscribedToMailingList() );
	}

	public function testScrubbedDonorSupportsReceiptRequirement(): void {
		$receiptRequiredDonor = new ScrubbedDonor( new ScrubbedName( 'Herr' ), DonorType::PERSON, false, true );
		$receiptNotRequiredDonor = new ScrubbedDonor( new ScrubbedName( 'Herr' ), DonorType::PERSON, false, false );
		$this->assertTrue( $receiptRequiredDonor->wantsReceipt() );
		$this->assertFalse( $receiptNotRequiredDonor->wantsReceipt() );
	}
}
