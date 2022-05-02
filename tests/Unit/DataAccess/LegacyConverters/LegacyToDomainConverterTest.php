<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\IncompleteDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter
 */
class LegacyToDomainConverterTest extends TestCase {

	public function testGivenIncompleteTrackingData_converterFillsTrackingDataWithDefaults(): void {
		$this->markTestIncomplete( 'This should work again when converter no longer creates dummy payment' );
		$doctrineDonation = IncompleteDoctrineDonation::newPaypalDonationWithMissingTrackingData();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$info = $donation->getTrackingInfo();

		$this->assertNotNull( $info );
		$this->assertSame( 0, $info->getTotalImpressionCount() );
		$this->assertSame( 0, $info->getSingleBannerImpressionCount() );
		$this->assertSame( '', $info->getTracking() );
	}

	public function testGivenDataSetWithExportDate_donationIsMarkedAsExported(): void {
		$this->markTestIncomplete( 'This should work again when converter no longer creates dummy payment' );
		$doctrineDonation = ValidDoctrineDonation::newExportedirectDebitDoctrineDonation();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );

		$this->assertTrue( $donation->isExported(), 'Donation should be marked as exported' );
	}

	public function testGivenDonationWithUnknownPayment_converterCreatesPaymentWithoutAssociatedData(): void {
		$this->markTestIncomplete( 'Talk to PM about this error condition - how backwards compatible should we be? See also https://phabricator.wikimedia.org/T304727' );
		// Commented out because we can't construct a doctrine donation with associated payment object
		// $doctrineDonation = ValidDoctrineDonation::newDonationWithCash();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$paymentMethod = $donation->getPaymentMethod();

		$this->assertInstanceOf( PaymentWithoutAssociatedData::class, $paymentMethod );
		$this->assertSame( 'CSH', $paymentMethod->getId() );
	}

	public function testGivenDonationWithCancelledStatus_converterMarksDonationAsCancelled(): void {
		$this->markTestIncomplete( 'This should work again when converter no longer creates dummy payment' );
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$this->assertTrue( $donation->isCancelled() );
	}

	public function testGivenDonationWithModerationReasons_converterMarksDonationAsToBeModerated(): void {
		$this->markTestIncomplete( 'This should work again when converter no longer creates dummy payment' );
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$moderationReasons = [
			new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN ),
			new ModerationReason( ModerationIdentifier::AMOUNT_TOO_HIGH )
			];
		$doctrineDonation->setModerationReasons( ...$moderationReasons );
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );

		$this->assertTrue( $donation->isMarkedForModeration() );
		$this->assertSame( $moderationReasons, $donation->getModerationReasons() );
	}
}
