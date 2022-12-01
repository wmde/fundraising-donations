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
		$doctrineDonation = ValidDoctrineDonation::newExportedirectDebitDoctrineDonation();
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );

		$this->assertTrue( $donation->isExported(), 'Donation should be marked as exported' );
	}

	public function testGivenDonationWithCancelledStatus_converterMarksDonationAsCancelled(): void {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );
		$this->assertTrue( $donation->isCancelled() );
	}

	public function testGivenDonationWithModerationReasons_converterMarksDonationAsToBeModerated(): void {
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

	/**
	 * @dataProvider donationProviderForNewsletterSubscription
	 */
	public function testConverterPassesNewsletterSubscriptionToDonor( DoctrineDonation $doctrineDonation, bool $expectedDonorValue ): void {
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );

		$this->assertSame( $expectedDonorValue, $donation->getDonor()->wantsNewsletter() );
	}

	/**
	 * @return iterable<array{DoctrineDonation, bool}>
	 */
	public function donationProviderForNewsletterSubscription(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setDonorOptsIntoNewsletter( true );
		yield 'private donor wants newsletter' => [ $doctrineDonation, true ];

		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setDonorOptsIntoNewsletter( false );
		yield 'private donor does not want newsletter' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newCompanyDonation();
		$doctrineDonation->setDonorOptsIntoNewsletter( true );
		yield 'company donor wants newsletter' => [ $doctrineDonation, true ];

		$doctrineDonation = ValidDoctrineDonation::newCompanyDonation();
		$doctrineDonation->setDonorOptsIntoNewsletter( false );
		yield 'company donor does not want newsletter' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newEmailDonation();
		$doctrineDonation->setDonorOptsIntoNewsletter( true );
		yield 'email-only donor wants newsletter' => [ $doctrineDonation, true ];

		$doctrineDonation = ValidDoctrineDonation::newEmailDonation();
		$doctrineDonation->setDonorOptsIntoNewsletter( false );
		yield 'email-only donor does not want newsletter' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newAnonymousDonation();
		yield 'anonymous donor cannot receive newsletter' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newAnonymousDonation();
		$doctrineDonation->setDonorOptsIntoNewsletter( true );
		yield 'converter ignores invalid newsletter subscription data from DB for anonymous' => [ $doctrineDonation, false ];
	}

	/**
	 * @dataProvider donationProviderForReceipt
	 */
	public function testConverterPassesReceiptRequirementsToDonor( DoctrineDonation $doctrineDonation, bool $expectedDonorValue ): void {
		$converter = new LegacyToDomainConverter();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );

		$this->assertSame( $expectedDonorValue, $donation->getDonor()->wantsReceipt() );
	}

	/**
	 * @return iterable<array{DoctrineDonation, bool}>
	 */
	public function donationProviderForReceipt(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setDonationReceipt( true );
		yield 'private donor wants receipt' => [ $doctrineDonation, true ];

		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setDonationReceipt( false );
		yield 'private donor does not want receipt' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setDonationReceipt( null );
		yield 'legacy private donor does not want receipt' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newCompanyDonation();
		$doctrineDonation->setDonationReceipt( true );
		yield 'company donor wants receipt' => [ $doctrineDonation, true ];

		$doctrineDonation = ValidDoctrineDonation::newCompanyDonation();
		$doctrineDonation->setDonationReceipt( false );
		yield 'company donor does not want receipt' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newCompanyDonation();
		$doctrineDonation->setDonationReceipt( null );
		yield 'legacy company donor does not want receipt' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newEmailDonation();
		$doctrineDonation->setDonationReceipt( true );
		yield 'converter ignores invalid receipt data from DB for email-only' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newEmailDonation();
		$doctrineDonation->setDonationReceipt( false );
		yield 'email-only donor does not want receipt' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newAnonymousDonation();
		yield 'anonymous donor cannot receive receipt' => [ $doctrineDonation, false ];

		$doctrineDonation = ValidDoctrineDonation::newAnonymousDonation();
		$doctrineDonation->setDonationReceipt( true );
		yield 'converter ignores invalid receipt data from DB for anonymous' => [ $doctrineDonation, false ];
	}
}
