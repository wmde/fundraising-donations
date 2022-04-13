<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;

/**
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donation
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor
 *
 * @license GPL-2.0-or-later
 */
class DonationTest extends TestCase {

	/**
	 * @dataProvider cancellableDonationProvider
	 */
	public function testGivenCancellableDonation_cancellationSucceeds( Donation $donation ): void {
		$donation->cancel();

		$this->assertTrue( $donation->isCancelled() );
	}

	public function cancellableDonationProvider(): iterable {
		yield [ ValidDonation::newDirectDebitDonation() ];
	}

	/**
	 * @dataProvider nonCancellableDonationProvider
	 */
	public function testGivenNonCancellableDonation_cancellationFails( Donation $donation ): void {
		$this->markTestIncomplete( 'Investigate why this fails' );
		$this->expectException( RuntimeException::class );
		$donation->cancel();
	}

	public function nonCancellableDonationProvider(): array {
		$exportedDonation = ValidDonation::newDirectDebitDonation();
		$exportedDonation->markAsExported();
		return [
			[ ValidDonation::newSofortDonation() ],
			[ ValidDonation::newBookedPayPalDonation() ],
			[ $exportedDonation ],
			[ ValidDonation::newCancelledPayPalDonation() ]
		];
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
			Donation::STATUS_NEW,
			ValidDonation::newDonor(),
			ValidDonation::newDirectDebitPayment(),
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
			Donation::STATUS_NEW,
			ValidDonation::newDonor(),
			ValidDonation::newDirectDebitPayment(),
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
			Donation::STATUS_NEW,
			ValidDonation::newDonor(),
			ValidDonation::newDirectDebitPayment(),
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
		$this->markTestIncomplete( 'booking should still do something, but independent from payment' );
		$donation = $this->newInModerationPayPalDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$donation->confirmBooked( ValidDonation::newPayPalData() );

		$this->assertFalse( $donation->getComment()->isPublic() );
	}

	public function testWhenCompletingBookingOfCancelledExternalPayment_commentIsMadePrivate(): void {
		$this->markTestIncomplete( 'booking should still do something, but independent from payment' );
		$donation = ValidDonation::newCancelledPayPalDonation();
		$donation->addComment( ValidDonation::newPublicComment() );

		$donation->confirmBooked( ValidDonation::newPayPalData() );

		$this->assertFalse( $donation->getComment()->isPublic() );
	}

	public function testWhenCompletingBookingOfCancelledExternalPayment_lackOfCommentCausesNoError(): void {
		$this->markTestIncomplete( 'booking should still do something, but independent from payment' );
		$donation = ValidDonation::newCancelledPayPalDonation();

		$donation->confirmBooked( ValidDonation::newPayPalData() );

		$this->assertFalse( $donation->hasComment() );
	}

	public function testWhenConstructingWithInvalidStatus_exceptionIsThrown(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Donation(
			null,
			'Such invalid status',
			ValidDonation::newDonor(),
			ValidDonation::newDirectDebitPayment(),
			Donation::OPTS_INTO_NEWSLETTER,
			ValidDonation::newTrackingInfo(),
			null
		);
	}

	private function makeGenericModerationReason(): ModerationReason {
		return new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN );
	}

	public function testMarkForModerationNeedsAtLeastOneModerationReason(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$this->expectException( \LogicException::class );
		$donation->markForModeration();
	}

}
