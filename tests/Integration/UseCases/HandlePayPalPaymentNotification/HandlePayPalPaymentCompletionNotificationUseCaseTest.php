<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\HandlePayPalPaymentNotification;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidPayPalNotificationRequest;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationRepositorySpy;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\ThrowingEntityManager;
use WMDE\Fundraising\DonationContext\Tests\Integration\DonationEventLoggerAsserter;
use WMDE\Fundraising\DonationContext\UseCases\DonationConfirmationNotifier;
use WMDE\Fundraising\DonationContext\UseCases\HandlePayPalPaymentNotification\HandlePayPalPaymentCompletionNotificationUseCase;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalData;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\HandlePayPalPaymentNotification\HandlePayPalPaymentCompletionNotificationUseCase
 *
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class HandlePayPalPaymentCompletionNotificationUseCaseTest extends TestCase {

	use DonationEventLoggerAsserter;

	public function testWhenRepositoryThrowsException_errorResponseIsReturned(): void {
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			new DoctrineDonationRepository( ThrowingEntityManager::newInstance( $this ) ),
			new FailingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);
		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$reponse = $useCase->handleNotification( $request );
		$this->assertFalse( $reponse->notificationWasHandled() );
		$this->assertTrue( $reponse->hasErrors() );
	}

	public function testWhenAuthorizationFails_unhandledResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newIncompletePayPalDonation() );

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new FailingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$this->assertFalse( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_successResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newIncompletePayPalDonation() );

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenPaymentTypeIsNonPayPal_unhandledResponseIsReturned(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newDirectDebitDonation() );

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$this->assertFalse( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_confirmationMailIsSent(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );

		$mailer = $this->getMailer();
		$mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $donation );

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$mailer,
			$this->getEventLogger()
		);

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testWhenAuthorizationSucceeds_donationIsStored(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$repositorySpy = new DonationRepositorySpy( $donation );

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$repositorySpy,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
		$this->assertCount( 1, $repositorySpy->getStoreDonationCalls() );
	}

	public function testWhenAuthorizationSucceeds_donationIsBooked(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$repository = new FakeDonationRepository( $donation );

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$repository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );
		$this->assertTrue( $repository->getDonationById( $donation->getId() )->isBooked() );
	}

	public function testWhenAuthorizationSucceeds_bookingEventIsLogged(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$repositorySpy = new DonationRepositorySpy( $donation );

		$eventLogger = new DonationEventLoggerSpy();

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$repositorySpy,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$eventLogger
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );

		$this->assertEventLogContainsExpression( $eventLogger, $donation->getId(), '/booked/' );
	}

	public function testGivenNewTransactionIdForBookedDonation_transactionIdShowsUpInChildPayments(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$transactionId = '16R12136PU8783961';

		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );

		$request = ValidPayPalNotificationRequest::newDuplicatePayment( $donation->getId(), $transactionId );

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );

		/** @var \WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment $payment */
		$payment = $fakeRepository->getDonationById( $donation->getId() )->getPaymentMethod();

		$this->assertTrue(
			$payment->getPayPalData()->hasChildPayment( $transactionId ),
			'Parent payment must have new transaction ID in its list'
		);
	}

	/**
	 * This test should be removed once we have Payments as their own domain,
	 * see https://phabricator.wikimedia.org/T192323
	 *
	 * @deprecated
	 */
	public function testGivenNewTransactionIdForBookedDonation_childTransactionWithSameDataIsCreated(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$donation->setOptsIntoDonationReceipt( true );
		$transactionId = '16R12136PU8783961';

		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );

		$request = ValidPayPalNotificationRequest::newDuplicatePayment( $donation->getId(), $transactionId );

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );

		$donation = $fakeRepository->getDonationById( $donation->getId() );
		/** @var \WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment $payment */
		$payment = $donation->getPaymentMethod();
		$childDonation = $fakeRepository->getDonationById(
			$payment->getPayPalData()->getChildPaymentEntityId( $transactionId )
		);
		$this->assertNotNull( $childDonation );
		/** @var \WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment $childDonationPaymentMethod */
		$childDonationPaymentMethod = $childDonation->getPaymentMethod();
		$this->assertEquals( $transactionId, $childDonationPaymentMethod->getPayPalData()->getPaymentId() );
		$this->assertEquals( $donation->getAmount(), $childDonation->getAmount() );
		$this->assertEquals( $donation->getDonor(), $childDonation->getDonor() );
		$this->assertEquals( $donation->getPaymentIntervalInMonths(), $childDonation->getPaymentIntervalInMonths() );
		$this->assertTrue( $childDonation->isBooked() );
		$this->assertTrue( $childDonation->getOptsIntoDonationReceipt() );
	}

	public function testGivenNewTransactionIdForBookedDonation_childCreationEventIsLogged(): void {
		$donation = ValidDonation::newBookedPayPalDonation();
		$transactionId = '16R12136PU8783961';

		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );

		$request = ValidPayPalNotificationRequest::newDuplicatePayment( $donation->getId(), $transactionId );

		$eventLogger = new DonationEventLoggerSpy();

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$eventLogger
		);

		$this->assertTrue( $useCase->handleNotification( $request )->notificationWasHandled() );

		$donation = $fakeRepository->getDonationById( $donation->getId() );
		/** @var \WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment $payment */
		$payment = $donation->getPaymentMethod();
		$childDonationId = $payment->getPayPalData()->getChildPaymentEntityId( $transactionId );

		$this->assertEventLogContainsExpression(
			$eventLogger,
			$donation->getId(),
			'/child donation.*' . $childDonationId . '/'
		);
		$this->assertEventLogContainsExpression(
			$eventLogger,
			$childDonationId,
			'/parent donation.*' . $donation->getId() . '/'
		);
	}

	public function testGivenExistingTransactionIdForBookedDonation_handlerReturnsFalse(): void {
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( ValidDonation::newBookedPayPalDonation() );

		$request = ValidPayPalNotificationRequest::newInstantPayment( 1 );

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$this->assertFalse( $useCase->handleNotification( $request )->notificationWasHandled() );
	}

	public function testGivenTransactionIsAlreadyBookedForDonation_notificationIsNotHandled(): void {
		$transactionId = '16R12136PU8783961';
		$donation = ValidDonation::newBookedPayPalDonation( $transactionId );
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );
		$request = ValidPayPalNotificationRequest::newDuplicatePayment( $donation->getId(), $transactionId );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$result = $useCase->handleNotification( $request );

		$this->assertFalse( $result->notificationWasHandled() );
		$this->assertNotEmpty( $result->getContext()['message'] );
		$this->assertStringContainsString( 'already booked', $result->getContext()['message'] );
	}

	public function testGivenTransactionIdInBookedChildDonation_notificationIsNotHandled(): void {
		$transactionId = '16R12136PU8783961';
		$fakeChildEntityId = 2;
		$donation = ValidDonation::newBookedPayPalDonation();
		$donation->getPaymentMethod()->getPaypalData()->addChildPayment( $transactionId, $fakeChildEntityId );
		$fakeRepository = new FakeDonationRepository();
		$fakeRepository->storeDonation( $donation );
		$request = ValidPayPalNotificationRequest::newDuplicatePayment( $donation->getId(), $transactionId );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$fakeRepository,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$result = $useCase->handleNotification( $request );

		$this->assertFalse( $result->notificationWasHandled() );
		$this->assertNotEmpty( $result->getContext()['message'] );
		$this->assertStringContainsString( 'already booked', $result->getContext()['message'] );
	}

	public function testWhenNotificationIsForNonExistingDonation_newDonationIsCreated(): void {
		$repositorySpy = new DonationRepositorySpy();

		$request = ValidPayPalNotificationRequest::newInstantPayment( 12345 );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$repositorySpy,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$useCase->handleNotification( $request );

		$storeDonationCalls = $repositorySpy->getStoreDonationCalls();
		$this->assertCount( 1, $storeDonationCalls, 'Donation is stored' );
		$this->assertNull( $storeDonationCalls[0]->getId(), 'ID is not taken from request' );
		$this->assertDonationIsCreatedWithNotficationRequestData( $storeDonationCalls[0] );
	}

	public function testGivenRecurringPaymentForIncompleteDonation_donationIsBooked(): void {
		$donation = ValidDonation::newIncompletePayPalDonation();
		$repositorySpy = new DonationRepositorySpy( $donation );

		$request = ValidPayPalNotificationRequest::newRecurringPayment( $donation->getId() );

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			$repositorySpy,
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$this->getEventLogger()
		);

		$useCase->handleNotification( $request );
		$donation = $repositorySpy->getDonationById( $donation->getId() );

		$this->assertCount( 1, $repositorySpy->getStoreDonationCalls() );
		$this->assertEquals( $donation, $repositorySpy->getStoreDonationCalls()[0] );
		$this->assertTrue( $donation->isBooked() );
	}

	public function testWhenNotificationIsForNonExistingDonation_confirmationMailIsSent(): void {
		$request = ValidPayPalNotificationRequest::newInstantPayment( 12345 );
		$mailer = $this->getMailer();
		$mailer->expects( $this->once() )
			->method( 'sendConfirmationFor' )
			->with( $this->anything() );
		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			new FakeDonationRepository(),
			new SucceedingDonationAuthorizer(),
			$mailer,
			$this->getEventLogger()
		);

		$useCase->handleNotification( $request );
	}

	public function testWhenNotificationIsForNonExistingDonation_bookingEventIsLogged(): void {
		$request = ValidPayPalNotificationRequest::newInstantPayment( 12345 );
		$eventLogger = new DonationEventLoggerSpy();

		$useCase = new HandlePayPalPaymentCompletionNotificationUseCase(
			new FakeDonationRepository(),
			new SucceedingDonationAuthorizer(),
			$this->getMailer(),
			$eventLogger
		);

		$useCase->handleNotification( $request );

		$expectedAutogeneratedDonationId = 1;
		$this->assertEventLogContainsExpression( $eventLogger, $expectedAutogeneratedDonationId, '/booked/' );
	}

	private function assertDonationIsCreatedWithNotficationRequestData( Donation $donation ): void {
		$this->assertSame( 0, $donation->getPaymentIntervalInMonths(), 'Direct payments should be always one-off donations' );
		$this->assertTrue( $donation->isBooked() );

		$donor = $donation->getDonor();
		$this->assertInstanceOf(
			AnonymousDonor::class,
			$donor,
			'Paypal payments without assigned donation assume a private person.'
		);

		$payment = $donation->getPayment();
		$this->assertSame( ValidPayPalNotificationRequest::AMOUNT_GROSS_CENTS, $payment->getAmount()->getEuroCents() );

		/** @var PayPalData $paypalData */
		$paypalData = $payment->getPaymentMethod()->getPaypalData();
		$this->assertSame( ValidPayPalNotificationRequest::PAYER_ADDRESS_NAME, $paypalData->getAddressName() );

		$this->assertNull( $donation->getOptsIntoDonationReceipt() );
	}

	/**
	 * @return DonationConfirmationNotifier&MockObject
	 */
	private function getMailer(): DonationConfirmationNotifier {
		return $this->getMockBuilder( DonationConfirmationNotifier::class )->disableOriginalConstructor()->getMock();
	}

	/**
	 * @return DonationEventLogger&MockObject
	 */
	private function getEventLogger(): DonationEventLogger {
		return $this->createMock( DonationEventLogger::class );
	}

}
