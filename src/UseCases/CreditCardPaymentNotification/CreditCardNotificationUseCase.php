<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\CreditCardPaymentNotification;

use Exception;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardTransactionData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Infrastructure\CreditCardService;

/**
 * @license GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class CreditCardNotificationUseCase {

	private $repository;
	private $authorizationService;
	private $creditCardService;
	private $mailer;
	private $donationEventLogger;

	public function __construct( DonationRepository $repository, DonationAuthorizer $authorizationService,
		CreditCardService $creditCardService, DonationConfirmationMailer $mailer,
		DonationEventLogger $donationEventLogger ) {
		$this->repository = $repository;
		$this->authorizationService = $authorizationService;
		$this->creditCardService = $creditCardService;
		$this->mailer = $mailer;
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

		if ( $donation->getPaymentMethodId() !== PaymentMethod::CREDIT_CARD ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::PAYMENT_TYPE_MISMATCH );
		}

		if ( !$donation->getAmount()->equals( $request->getAmount() ) ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::AMOUNT_MISMATCH );
		}

		if ( !$this->authorizationService->systemCanModifyDonation( $request->getDonationId() ) ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::AUTHORIZATION_FAILED );
		}

		return $this->handleRequest( $request, $donation );
	}

	private function handleRequest( CreditCardPaymentNotificationRequest $request, Donation $donation ): CreditCardNotificationResponse {
		try {
			$donation->addCreditCardData( $this->newCreditCardDataFromRequest( $request ) );
			$donation->confirmBooked();
		}
		catch ( \RuntimeException $e ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::DOMAIN_ERROR, $e );
		}

		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return CreditCardNotificationResponse::newFailureResponse( CreditCardNotificationResponse::DOMAIN_ERROR, $ex );
		}

		$this->donationEventLogger->log( $donation->getId(), 'mcp_handler: booked' );

		return CreditCardNotificationResponse::newSuccessResponse( $this->sendConfirmationEmail( $donation ) );
	}

	private function sendConfirmationEmail( Donation $donation ): ?Exception {
		if ( $donation->getDonor() !== null ) {
			try {
				$this->mailer->sendConfirmationMailFor( $donation );
			}
			catch ( Exception $ex ) {
				return $ex;
			}
		}
		return null;
	}

	private function newCreditCardDataFromRequest( CreditCardPaymentNotificationRequest $request ): CreditCardTransactionData {
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
	}

}
