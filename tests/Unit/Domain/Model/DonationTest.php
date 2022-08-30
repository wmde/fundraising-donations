<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayments;

/**
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donation
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor
 *
 * @license GPL-2.0-or-later
 */
class DonationTest extends TestCase {

	public function testCancelingADonationSucceeds(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->cancel();

		$this->assertTrue( $donation->isCancelled() );
	}

	public function testModerationStatusCanBeQueried(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->markForModeration( $this->makeGenericModerationReason() );
		$this->assertTrue( $donation->isMarkedForModeration() );
	}

	public function testGivenModerationStatus_cancellationSucceeds(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->markForModeration( $this->makeGenericModerationReason() );
		$donation->cancel();
		$this->assertTrue( $donation->isCancelled() );
	}

	public function testIdIsNullWhenNotAssigned(): void {
		$this->assertNull( ValidDonation::newDirectDebitDonation()->getId() );
	}

	public function testCanAssignIdToNewDonation(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->assignId( 42 );
		$this->assertSame( 42, $donation->getId() );
	}

	public function testCannotAssignIdToDonationWithIdentity(): void {
		$donation = ValidDonation::newDirectDebitDonation();
		$donation->assignId( 42 );

		$this->expectException( RuntimeException::class );
		$donation->assignId( 43 );
	}

	public function testNewDonationsAreNotExported() {
		$donation = new Donation(
			null,
			ValidDonation::newDonor(),
			ValidPayments::newDirectDebitPayment()->getId(),
			Donation::OPTS_INTO_NEWSLETTER,
			ValidDonation::newTrackingInfo(),
			null
		);
		$this->assertFalse( $donation->isExported() );
	}

	private function newInModerationPayPalDonation(): Donation {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$donation->markForModeration( $this->makeGenericModerationReason() );
		return $donation;
	}

	public function testAddCommentThrowsExceptionWhenCommentAlreadySet(): void {
		$donation = new Donation(
			null,
			ValidDonation::newDonor(),
			ValidPayments::newDirectDebitPayment()->getId(),
			Donation::OPTS_INTO_NEWSLETTER,
			ValidDonation::newTrackingInfo(),
			ValidDonation::newPublicComment()
		);

		$this->expectException( RuntimeException::class );
		$donation->addComment( ValidDonation::newPublicComment() );
	}

	public function testAddCommentSetsWhenCommentNotSetYet(): void {
		$donation = new Donation(
			null,
			ValidDonation::newDonor(),
			ValidPayments::newDirectDebitPayment()->getId(),
			Donation::OPTS_INTO_NEWSLETTER,
			ValidDonation::newTrackingInfo(),
			null
		);

		$donation->addComment( ValidDonation::newPublicComment() );
		$this->assertEquals( ValidDonation::newPublicComment(), $donation->getComment() );
	}

	public function testWhenNoCommentHasBeenSet_getCommentReturnsNull(): void {
		$this->assertNull( ValidDonation::newDirectDebitDonation()->getComment() );
	}

	public function testWhenCompletingBookingOfExternalPaymentInModeration_commentIsMadePrivate(): void {
		$donation = $this->newInModerationPayPalDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$donation->confirmBooked();

		$this->assertFalse( $donation->getComment()->isPublic() );
	}

	public function testWhenCompletingBookingOfCancelledExternalPayment_commentIsMadePrivate(): void {
		$donation = ValidDonation::newCancelledPayPalDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$donation->confirmBooked();

		$this->assertFalse( $donation->getComment()->isPublic() );
	}

	public function testWhenCompletingBookingOfCancelledExternalPayment_lackOfCommentCausesNoError(): void {
		$donation = ValidDonation::newCancelledPayPalDonation();

		$donation->confirmBooked();

		$this->assertFalse( $donation->hasComment() );
	}

	private function makeGenericModerationReason(): ModerationReason {
		return new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
	}

	public function testCreateFollowupDonationForPayment_duplicatesRelevantFields(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$followupUpDonation = $donation->createFollowupDonationForPayment( paymentId: 99 );

		$this->assertSame( 99, $followupUpDonation->getPaymentId() );
		$this->assertEquals( $followupUpDonation->getDonor(), $donation->getDonor() );
		$this->assertEquals( $followupUpDonation->getTrackingInfo(), $donation->getTrackingInfo() );
		$this->assertEquals( $followupUpDonation->getOptsIntoNewsletter(), $donation->getOptsIntoNewsletter() );
		$this->assertFalse( $followupUpDonation->isExported() );
	}

	public function testMarkForModerationNeedsAtLeastOneModerationReason(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$this->expectException( \LogicException::class );
		$donation->markForModeration();
	}

}
