<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\LegacyConverters;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation as DoctrineDonation;
use WMDE\Fundraising\DonationContext\DataAccess\LegacyConverters\LegacyToDomainConverter;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\IncompleteDoctrineDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDoctrineDonation;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentWithoutAssociatedData;

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

	/**
	 * @dataProvider cancelledDonations
	 */
	public function testGivenDonationWithCancelledStatus_DonationStatusMatchesPaymentType( DoctrineDonation $dd, string $expectedStatus ): void {
		$this->markTestIncomplete( 'Donation status derived from payment needs to be reworked' );
		$converter = new LegacyToDomainConverter();
		$donation = $converter->createFromLegacyObject( $dd );

		$this->assertSame( $expectedStatus, $donation->getStatus() );
	}

	public function cancelledDonations(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_PROMISE ];

		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_NEW ];

		// this case should never occur, but tested anyway
		$doctrineDonation = ValidDoctrineDonation::newIncompletePaypalDonation();
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE ];
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

	/**
	 * @dataProvider donationsMarkedForModeration
	 */
	public function testGivenDonationWithModerationNeededStatus_DonationStatusMatchesPaymentType( DoctrineDonation $dd, string $expectedStatus ): void {
		$this->markTestIncomplete( 'Donation status derived from payment needs to be reworked' );
		$converter = new LegacyToDomainConverter();
		$donation = $converter->createFromLegacyObject( $dd );

		$this->assertSame( $expectedStatus, $donation->getStatus() );
	}

	public function donationsMarkedForModeration(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newBankTransferDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_PROMISE ];

		$doctrineDonation = ValidDoctrineDonation::newDirectDebitDoctrineDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_NEW ];

		// this case should never occur, but tested anyway
		$doctrineDonation = ValidDoctrineDonation::newIncompletePaypalDonation();
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE ];
	}

	/**
	 * @dataProvider donationsWithExternalPaymentAndModifiedState
	 */
	public function testGivenCanceledOrModeratedDonationWithExternalPayment_statusReflectsPaymentState( DoctrineDonation $dd, string $expectedStatus, string $description ): void {
		$this->markTestIncomplete( 'Donation status derived from payment needs to be reworked' );
		$converter = new LegacyToDomainConverter();
		$donation = $converter->createFromLegacyObject( $dd );

		$this->assertSame( $expectedStatus, $donation->getStatus(), "Failed expectation for: $description" );
	}

	public function donationsWithExternalPaymentAndModifiedState(): iterable {
		$doctrineDonation = ValidDoctrineDonation::newIncompletePaypalDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Moderated incomplete paypal donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Canceled incomplete paypal donation (broken legacy data)' ];

		$doctrineDonation = ValidDoctrineDonation::newPaypalDoctrineDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Moderated booked paypal donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Canceled booked paypal donation (broken legacy data)' ];

		$doctrineDonation = ValidDoctrineDonation::newIncompleteCreditCardDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Moderated incomplete credit card donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_INCOMPLETE, 'Canceled incomplete credit card donation (broken legacy data)' ];

		$doctrineDonation = ValidDoctrineDonation::newCreditCardDonation();
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_MODERATION );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Moderated booked credit card donation' ];

		// Some legacy donations were canceled, even when that's not possible now
		$doctrineDonation->setStatus( DoctrineDonation::STATUS_CANCELLED );
		yield [ $doctrineDonation, Donation::STATUS_EXTERNAL_BOOKED, 'Canceled booked credit card donation (broken legacy data)' ];
	}

	/**
	 * Remove this test when we remove status from Donation, see https://phabricator.wikimedia.org/T281853
	 */
	public function testGivenUnknownPaymentMethod_stateIsPromised(): void {
		$this->markTestIncomplete( 'Donation status derived from payment needs to be reworked' );
		$converter = new LegacyToDomainConverter();

		// Commented out because we can't construct the doctrine donation with a payment instance any more
		// $doctrineDonation = ValidDoctrineDonation::newDonationWithCash();

		$donation = $converter->createFromLegacyObject( $doctrineDonation );

		$this->assertSame( Donation::STATUS_PROMISE, $donation->getStatus() );
	}
}
