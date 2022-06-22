<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\SofortPaymentNotification;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidSofortNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\SofortPaymentNotification\SofortPaymentNotificationUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\SofortPaymentNotification\SofortPaymentNotificationUseCase
 */
class SofortPaymentNotificationUseCaseTest extends TestCase {

	public function testWhenRepositoryThrowsException_errorResponseIsReturned(): void {
		$useCase = new SofortPaymentNotificationUseCase(
			new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) ),
			new SucceedingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$response = $useCase->handleNotification( $request );
		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertTrue( $response->hasErrors() );
	}

	/**
	 * @return DonationNotifier&MockObject
	 */
	private function getMailer(): DonationNotifier {
		return $this->createMock( DonationNotifier::class );
	}

	public function testWhenNotificationIsForNonExistingDonation_unhandledResponseIsReturned(): void {
		$useCase = new SofortPaymentNotificationUseCase(
			new FakeDonationRepository(),
			new SucceedingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment( 4711 );

		$this->assertFalse( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationFails_unhandledResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newIncompleteSofortDonation() );

		$useCase = new SofortPaymentNotificationUseCase(
			$fakeRepository,
			new FailingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$this->assertFalse( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_successResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newIncompleteSofortDonation() );

		$useCase = new SofortPaymentNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_donationIsStored(): void {
		$repositorySpy = new DonationRepositorySpy( ValidDonation::newIncompleteSofortDonation() );

		$useCase = new SofortPaymentNotificationUseCase(
			$repositorySpy,
			new SucceedingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
		$this->assertCount( 1, $repositorySpy->getStoreDonationCalls() );
	}

	public function testWhenAuthorizationSucceeds_donationIsStillBooked(): void {
		$donation = ValidDonation::newIncompleteSofortDonation();
		$repository = new FakeDonationRepository( $donation );

		$useCase = new SofortPaymentNotificationUseCase(
			$repository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
		$this->assertTrue( $donation->isBooked() );
	}

	public function testWhenPaymentTypeIsNonSofort_unhandledResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newDirectDebitDonation() );

		$useCase = new SofortPaymentNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$this->assertFalse( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_confirmationMailIsSent(): void {
		$donation = ValidDonation::newIncompleteSofortDonation();
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );

		$mailer = $this->getMailer();
		$mailer
			->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $donation );

		$useCase = new SofortPaymentNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$mailer
		);

		$request = ValidSofortNotificationRequest::newInstantPayment( 1 );
		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testGivenSetConfirmedAtForBookedDonation_unhandledResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newCompletedSofortDonation() );

		$useCase = new SofortPaymentNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer()
		);

		$request = ValidSofortNotificationRequest::newInstantPayment();

		$response = $useCase->handleNotification( $request );
		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertFalse( $response->hasErrors() );
		$this->assertSame( 'Duplicate notification', $response->getContext()['message'] );
	}

}
