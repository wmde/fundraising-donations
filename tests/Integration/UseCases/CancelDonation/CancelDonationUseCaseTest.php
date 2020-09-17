<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\CancelDonation;

use WMDE\EmailAddress\EmailAddress;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Infrastructure\TemplateMailerInterface;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\TemplateBasedMailerSpy;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationResponse;
use WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationUseCase;

/**
 * @covers WMDE\Fundraising\DonationContext\UseCases\CancelDonation\CancelDonationUseCase
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class CancelDonationUseCaseTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @var FakeDonationRepository
	 */
	private $repository;

	/**
	 * @var TemplateMailerInterface|TemplateBasedMailerSpy
	 */
	private $mailer;

	/**
	 * @var DonationAuthorizer
	 */
	private $authorizer;

	/**
	 * @var DonationEventLoggerSpy
	 */
	private $logger;

	public function setUp(): void {
		$this->repository = new FakeDonationRepository();
		$this->mailer = new TemplateBasedMailerSpy( $this );
		$this->authorizer = new SucceedingDonationAuthorizer();
		$this->logger = new DonationEventLoggerSpy();
	}

	private function newCancelDonationUseCase(): CancelDonationUseCase {
		return new CancelDonationUseCase(
			$this->repository,
			$this->mailer,
			$this->authorizer,
			$this->logger
		);
	}

	public function testGivenIdOfUnknownDonation_cancellationIsNotSuccessful(): void {
		$response = $this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1 ) );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	public function testResponseContainsDonationId(): void {
		$response = $this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1337 ) );

		$this->assertSame( 1337, $response->getDonationId() );
	}

	public function testGivenIdOfCancellableDonation_cancellationIsSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );
		$this->assertFalse( $response->mailDeliveryFailed() );

		$this->assertTrue( $this->repository->getDonationById( $donation->getId() )->isCancelled() );
	}

	public function testGivenIdOfNonCancellableDonation_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$donation->cancel();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	private function newCancelableDonation(): Donation {
		return ValidDonation::newDirectDebitDonation();
	}

	public function testWhenDonationGetsCancelled_cancellationConfirmationEmailIsSent(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertTrue( $response->cancellationSucceeded() );

		$this->mailer->assertCalledOnceWith(
			new EmailAddress( $donation->getDonor()->getEmailAddress() ),
			[
				'recipient' => [
					'salutation' => ValidDonation::DONOR_SALUTATION,
					'title' => ValidDonation::DONOR_TITLE,
					'firstName' => ValidDonation::DONOR_FIRST_NAME,
					'lastName' => ValidDonation::DONOR_LAST_NAME,
					'companyName' => ''
				],
				'donationId' => 1
			]
		);
	}

	public function testWhenDonationGetsCancelled_logEntryNeededByBackendIsWritten(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( $donation->getId() ) );

		$this->assertSame(
			[ [ $donation->getId(), 'frontend: storno' ] ],
			$this->logger->getLogCalls()
		);
	}

	public function testGivenIdOfNonCancellableDonation_nothingIsWrittenToTheLog(): void {
		$this->newCancelDonationUseCase()->cancelDonation( new CancelDonationRequest( 1 ) );

		$this->assertSame( [], $this->logger->getLogCalls() );
	}

	public function testWhenConfirmationMailFails_mailDeliveryFailureResponseIsReturned(): void {
		$this->mailer = $this->newThrowingMailer();

		$response = $this->getResponseForCancellableDonation();

		$this->assertTrue( $response->cancellationSucceeded() );
		$this->assertTrue( $response->mailDeliveryFailed() );
	}

	private function newThrowingMailer(): TemplateMailerInterface {
		$mailer = $this->createMock( TemplateMailerInterface::class );

		$mailer->method( $this->anything() )->willThrowException( new \RuntimeException() );

		return $mailer;
	}

	public function testWhenGetDonationFails_cancellationIsNotSuccessful(): void {
		$this->repository->throwOnRead();

		$response = $this->getResponseForCancellableDonation();

		$this->assertFalse( $response->cancellationSucceeded() );
	}

	private function getResponseForCancellableDonation(): CancelDonationResponse {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$request = new CancelDonationRequest( $donation->getId() );
		return $this->newCancelDonationUseCase()->cancelDonation( $request );
	}

	public function testWhenDonationSavingFails_cancellationIsNotSuccessful(): void {
		$donation = $this->newCancelableDonation();
		$this->repository->storeDonation( $donation );

		$this->repository->throwOnWrite();

		$request = new CancelDonationRequest( $donation->getId() );
		$response = $this->newCancelDonationUseCase()->cancelDonation( $request );

		$this->assertFalse( $response->cancellationSucceeded() );
	}

}
