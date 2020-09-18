<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\HandlePayPalPaymentNotification;

use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationPayment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\RequestModel\PayPalPaymentNotificationRequest;
use WMDE\Fundraising\PaymentContext\ResponseModel\PaypalNotificationResponse;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class HandlePayPalPaymentCompletionNotificationUseCase {

	private $repository;
	private $authorizationService;
	private $mailer;
	private $donationEventLogger;

	public function __construct( DonationRepository $repository, DonationAuthorizer $authorizationService,
		DonationConfirmationMailer $mailer, DonationEventLogger $donationEventLogger ) {
		$this->repository = $repository;
		$this->authorizationService = $authorizationService;
		$this->mailer = $mailer;
		$this->donationEventLogger = $donationEventLogger;
	}

	public function handleNotification( PayPalPaymentNotificationRequest $request ): PaypalNotificationResponse {
		try {
			$donation = $this->repository->getDonationById( $request->getInternalId() );
		}
		catch ( GetDonationException $ex ) {
			return $this->createErrorResponse( $ex );
		}

		if ( $donation === null ) {
			return $this->handleRequestWithoutDonation( $request );
		}

		return $this->handleRequestForDonation( $request, $donation );
	}

	private function handleRequestWithoutDonation( PayPalPaymentNotificationRequest $request ): PaypalNotificationResponse {
		$donation = $this->newDonationFromRequest( $request );

		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return $this->createErrorResponse( $ex );
		}

		$this->mailer->sendConfirmationMailFor( $donation );
		$this->donationEventLogger->log( $donation->getId(), 'paypal_handler: booked' );

		return PaypalNotificationResponse::newSuccessResponse();
	}

	private function handleRequestForDonation( PayPalPaymentNotificationRequest $request, Donation $donation ): PaypalNotificationResponse {
		$paymentMethod = $donation->getPayment()->getPaymentMethod();

		if ( !( $paymentMethod instanceof PayPalPayment ) ) {
			return $this->createUnhandledResponse( 'Trying to handle IPN for non-Paypal donation' );
		}

		if ( !$this->authorizationService->systemCanModifyDonation( $request->getInternalId() ) ) {
			return $this->createUnhandledResponse( 'Wrong access code for donation' );
		}
		if ( $this->donationWasBookedWithSameTransactionId( $donation, $request->getTransactionId() ) ) {
			return $this->createUnhandledresponse(
				sprintf(
					'Transaction id "%s" already booked for donation id %d',
					$request->getTransactionId(),
					$donation->getId()
				) );
		}
		if ( $this->isFollowupPaymentForRecurringDonation( $donation ) ) {
			return $this->createChildDonation( $donation, $request );
		}

		$paymentMethod->addPayPalData( $this->newPayPalDataFromRequest( $request ) );

		try {
			$donation->confirmBooked();
		}
		catch ( \RuntimeException $ex ) {
			return $this->createErrorResponse( $ex );
		}

		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return $this->createErrorResponse( $ex );
		}

		$this->mailer->sendConfirmationMailFor( $donation );
		$this->donationEventLogger->log( $donation->getId(), 'paypal_handler: booked' );

		return PaypalNotificationResponse::newSuccessResponse();
	}

	private function createUnhandledResponse( string $reason ): PaypalNotificationResponse {
		return PaypalNotificationResponse::newUnhandledResponse(
			[
				'message' => $reason
			]
		);
	}

	private function newPayPalDataFromRequest( PayPalPaymentNotificationRequest $request ): PayPalData {
		return ( new PayPalData() )
			->setPayerId( $request->getPayerId() )
			->setSubscriberId( $request->getSubscriptionId() )
			->setPayerStatus( $request->getPayerStatus() )
			->setAddressStatus( $request->getPayerAddressStatus() )
			->setAmount( $request->getAmountGross() )
			->setCurrencyCode( $request->getCurrencyCode() )
			->setFee( Euro::newFromString( $request->getTransactionFee() ) )
			->setSettleAmount( $request->getSettleAmount() )
			->setFirstName( $request->getPayerFirstName() )
			->setLastName( $request->getPayerLastName() )
			->setAddressName( $request->getPayerAddressName() )
			->setPaymentId( $request->getTransactionId() )
			->setPaymentType( $request->getPaymentType() )
			->setPaymentStatus( implode( '/', [ $request->getPaymentStatus(), $request->getTransactionType() ] ) )
			->setPaymentTimestamp( $request->getPaymentTimestamp() );
	}

	private function isFollowupPaymentForRecurringDonation( Donation $donation ): bool {
		return $donation->getPayment()->getIntervalInMonths() > 0 && $donation->isBooked();
	}

	private function createChildDonation( Donation $donation, PayPalPaymentNotificationRequest $request ): PaypalNotificationResponse {
		$childPaymentMethod = new PayPalPayment( $this->newPayPalDataFromRequest( $request ) );
		$payment = $donation->getPayment();
		$childDonation = new Donation(
			null,
			Donation::STATUS_EXTERNAL_BOOKED,
			$donation->getDonor(),
			new DonationPayment( $payment->getAmount(), $payment->getIntervalInMonths(), $childPaymentMethod ),
			$donation->getOptsIntoNewsletter(), $donation->getTrackingInfo()
		);
		$childDonation->setOptsIntoDonationReceipt( $donation->getOptsIntoDonationReceipt() );

		try {
			$this->repository->storeDonation( $childDonation );
		}
		catch ( StoreDonationException $ex ) {
			return $this->createErrorResponse( $ex );
		}
		/** @var \WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment $paymentMethod */
		$paymentMethod = $payment->getPaymentMethod();
		$paymentMethod->getPayPalData()->addChildPayment( $request->getTransactionId(), $childDonation->getId() );
		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return $this->createErrorResponse( $ex );
		}
		$this->logChildDonationCreatedEvent( $donation->getId(), $childDonation->getId() );
		return PaypalNotificationResponse::newSuccessResponse();
	}

	private function logChildDonationCreatedEvent( $parentId, $childId ): void {    // @codingStandardsIgnoreLine
		$this->donationEventLogger->log(
			$parentId,
			"paypal_handler: new transaction id to corresponding child donation: $childId"
		);
		$this->donationEventLogger->log(
			$childId,
			"paypal_handler: new transaction id to corresponding parent donation: $parentId"
		);
	}

	private function newDonorFromRequest( PayPalPaymentNotificationRequest $request ): PersonDonor {
		return new PersonDonor(
			new PersonName( $request->getPayerFirstName(), $request->getPayerLastName(), '', '' ),
			$this->newPhysicalAddressFromRequest( $request ),
			$request->getPayerEmail()
		);
	}

	private function newPhysicalAddressFromRequest( PayPalPaymentNotificationRequest $request ): PostalAddress {
		return new PostalAddress(
			$request->getPayerAddressStreet(),
			$request->getPayerAddressPostalCode(),
			$request->getPayerAddressCity(),
			$request->getPayerAddressCountryCode()
		);
	}

	private function newDonationFromRequest( PayPalPaymentNotificationRequest $request ): Donation {
		$payment = new DonationPayment(
			$request->getAmountGross(),
			0,
			new PayPalPayment( $this->newPayPalDataFromRequest( $request ) )
		);

		return new Donation(
			null,
			Donation::STATUS_EXTERNAL_BOOKED,
			$this->newDonorFromRequest( $request ),
			$payment,
			Donation::DOES_NOT_OPT_INTO_NEWSLETTER,
			DonationTrackingInfo::newBlankTrackingInfo()->freeze()->assertNoNullFields()
		);
	}

	/**
	 * @todo Move this check to the payment domain use case, see https://phabricator.wikimedia.org/T192323
	 *
	 * @param Donation $donation
	 * @param string $transactionId
	 *
	 * @return bool
	 */
	private function donationWasBookedWithSameTransactionId( Donation $donation, string $transactionId ): bool {
		/**
		 * @var PayPalPayment $payment
		 */
		$payment = $donation->getPaymentMethod();

		if ( $payment->getPayPalData()->getPaymentId() === $transactionId ) {
			return true;
		}

		return $payment->getPayPalData()->hasChildPayment( $transactionId );
	}

	private function createErrorResponse( \Exception $ex ): PaypalNotificationResponse {
		return PaypalNotificationResponse::newFailureResponse(
			[
				'message' => $ex->getMessage(),
				'stackTrace' => $ex->getTraceAsString()
			]
		);
	}

}
