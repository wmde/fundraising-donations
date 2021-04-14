<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
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

	public function testGivenNonDirectDebitDonation_cancellationFails(): void {
		$donation = ValidDonation::newBankTransferDonation();

		$this->expectException( RuntimeException::class );
		$donation->cancel();
	}

	public function testGivenDirectDebitDonation_cancellationSucceeds(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->cancel();
		$this->assertTrue( $donation->isCancelled() );
	}

	/**
	 * @dataProvider nonCancellableDonationProvider
	 */
	public function testGivenNonCancellableDonation_cancellationFails( Donation $donation ): void {
		$this->expectException( RuntimeException::class );
		$donation->cancel();
	}

	public function nonCancellableDonationProvider(): array {
		$exportedDonation = ValidDonation::newDirectDebitDonation();
		$exportedDonation->markAsExported();
		return [
			[ ValidDonation::newBankTransferDonation() ],
			[ ValidDonation::newSofortDonation() ],
			[ ValidDonation::newBookedPayPalDonation() ],
			[ $exportedDonation ],
			[ ValidDonation::newCancelledPayPalDonation() ]
		];
	}

	public function testGivenNewStatus_cancellationSucceeds(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->cancel();
		$this->assertTrue( $donation->isCancelled() );
	}

	public function testModerationStatusCanBeQueried(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->markForModeration();
		$this->assertTrue( $donation->needsModeration() );
	}

	public function testGivenModerationStatus_cancellationSucceeds(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$donation->markForModeration();
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

	public function testGivenNonExternalPaymentType_confirmBookedThrowsException(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Only external payments/' );
		$donation->confirmBooked();
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

	/**
	 * @dataProvider statusesThatDoNotAllowForBookingProvider
	 */
	public function testGivenStatusThatDoesNotAllowForBooking_confirmBookedThrowsException( Donation $donation ): void {
		$this->expectException( RuntimeException::class );
		$donation->confirmBooked();
	}

	public function statusesThatDoNotAllowForBookingProvider(): array {
		return [
			[ ValidDonation::newBookedPayPalDonation() ],
			[ ValidDonation::newBookedCreditCardDonation() ],
		];
	}

	/**
	 * @dataProvider statusesThatAllowsForBookingProvider
	 */
	public function testGivenStatusThatAllowsForBooking_confirmBookedSetsBookedStatus( Donation $donation ): void {
		$donation->confirmBooked();
		$this->assertSame( Donation::STATUS_EXTERNAL_BOOKED, $donation->getStatus() );
	}

	public function statusesThatAllowsForBookingProvider(): array {
		return [
			[ ValidDonation::newIncompletePayPalDonation() ],
			[ ValidDonation::newIncompleteCreditCardDonation() ],
			[ $this->newInModerationPayPalDonation() ],
			[ ValidDonation::newCancelledPayPalDonation() ],
		];
	}

	private function newInModerationPayPalDonation(): Donation {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$donation->markForModeration();
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

	public function testWhenNonExternalPaymentIsNotifiedOfPolicyValidationFailure_itIsPutInModeration(): void {
		$donation = ValidDonation::newBankTransferDonation();
		$donation->notifyOfPolicyValidationFailure();
		$this->assertTrue( $donation->needsModeration() );
	}

	public function testWhenExternalPaymentIsNotifiedOfPolicyValidationFailure_itIsNotPutInModeration(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$donation->notifyOfPolicyValidationFailure();
		$this->assertFalse( $donation->needsModeration() );
	}

}
