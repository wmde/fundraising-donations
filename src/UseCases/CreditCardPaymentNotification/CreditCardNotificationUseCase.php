<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CreditCardNotificationUseCase {

	private DonationRepository $repository;
	private DonationAuthorizer $authorizationService;
	private DonationNotifier $notifier;
	private DonationEventLogger $donationEventLogger;

	public function __construct( DonationRepository $repository, DonationAuthorizer $authorizationService,
								 DonationNotifier $notifier,
		DonationEventLogger $donationEventLogger ) {
		$this->repository = $repository;
		$this->authorizationService = $authorizationService;
		$this->notifier = $notifier;
		$this->donationEventLogger = $donationEventLogger;
	}

	public function handleNotification( CreditCardPaymentNotificationRequest $request ): CreditCardNotificationResponse {
		try {
			$donation = $this->repository->getDonationById( $request->getDonationId() );
		}
		catch ( GetDonationException $ex ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::DATABASE_ERROR, $ex );
		}

		if ( $donation === null ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::DONATION_NOT_FOUND );
		}

		/**
		 * if ( $donation->getPaymentMethodId() !== PaymentMethod::CREDIT_CARD ) {
		 * return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::PAYMENT_TYPE_MISMATCH );
		 * }
		 *
		 * if ( !$donation->getAmount()->equals( $request->getAmount() ) ) {
		 * return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::AMOUNT_MISMATCH );
		 * }
		 */

		if ( !$this->authorizationService->systemCanModifyDonation( $request->getDonationId() ) ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::AUTHORIZATION_FAILED );
		}

		return $this->handleRequest( $request, $donation );
	}

	private function handleRequest( CreditCardPaymentNotificationRequest $request, Donation $donation ): CreditCardNotificationResponse {
		$donation->confirmBooked();

		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::DOMAIN_ERROR, $ex );
		}

		$this->donationEventLogger->log( $donation->getId(), 'mcp_handler: booked' );

		$this->notifier->sendConfirmationFor( $donation );

		return CreditCardNotificationResponse::newSuccessResponse( null );
	}

	/*private function newCreditCardDataFromRequest( CreditCardPaymentNotificationRequest $request ): array {

		return ( new CreditCardTransactionData() )
			->setTransactionId( $request->getTransactionId() )
			->setTransactionStatus( 'processed' )
			->setTransactionTimestamp( new \DateTime() )
			->setCardExpiry( $this->creditCardService->getExpirationDate( $request->getCustomerId() ) )
			->setAmount( $request->getAmount() )
			->setCustomerId( $request->getCustomerId() )
			->setSessionId( $request->getSessionId() )
			->setAuthId( $request->getAuthId() )
			->setTitle( $request->getTitle() )
			->setCountryCode( $request->getCountry() )
			->setCurrencyCode( $request->getCurrency() );

		return [];
	}*/
}
