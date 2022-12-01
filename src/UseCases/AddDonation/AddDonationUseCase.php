<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokens;
use WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\PaymentUrlGenerator\PaymentProviderURLGenerator;
use WMDE\Fundraising\PaymentContext\Domain\PaymentUrlGenerator\RequestContext;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @license GPL-2.0-or-later
 */
class AddDonationUseCase {

	private const PREFIX_BANK_TRANSACTION_KNOWN_DONOR = 'XW';
	private const PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR = 'XR';

	private DonationRepository $donationRepository;
	private AddDonationValidator $donationValidator;
	private ModerationService $policyValidator;
	private DonationNotifier $notifier;
	private DonationTokenFetcher $tokenFetcher;
	private EventEmitter $eventEmitter;
	private CreatePaymentService $paymentService;

	public function __construct( DonationRepository $donationRepository, AddDonationValidator $donationValidator,
								 ModerationService $policyValidator, DonationNotifier $notifier,
								 DonationTokenFetcher $tokenFetcher,
								 EventEmitter $eventEmitter, CreatePaymentService $paymentService ) {
		$this->donationRepository = $donationRepository;
		$this->donationValidator = $donationValidator;
		$this->policyValidator = $policyValidator;
		$this->notifier = $notifier;
		$this->tokenFetcher = $tokenFetcher;
		$this->eventEmitter = $eventEmitter;
		$this->paymentService = $paymentService;
	}

	public function addDonation( AddDonationRequest $donationRequest ): AddDonationResponse {
		$validationResult = $this->donationValidator->validate( $donationRequest );

		if ( $validationResult->hasViolations() ) {
			return AddDonationResponse::newFailureResponse( $validationResult->getViolations() );
		}

		$paymentResult = $this->paymentService->createPayment( $this->getPaymentRequestForDonor( $donationRequest ) );
		if ( $paymentResult instanceof FailureResponse ) {
			return AddDonationResponse::newFailureResponse( [
				new ConstraintViolation( $donationRequest->getPaymentCreationRequest(), $paymentResult->errorMessage, 'payment' )
			] );
		}
		$donation = $this->newDonationFromRequest( $donationRequest, $paymentResult->paymentId );

		$moderationResult = $this->policyValidator->moderateDonationRequest( $donationRequest );
		if ( $moderationResult->needsModeration() ) {
			$donation->markForModeration( ...$moderationResult->getViolations() );
		}

		if ( $this->policyValidator->isAutoDeleted( $donationRequest ) ) {
			$donation->cancelWithoutChecks();
		}

		$this->donationRepository->storeDonation( $donation );

		$tokens = $this->tokenFetcher->getTokens( $donation->getId() );

		$this->eventEmitter->emit( new DonationCreatedEvent( $donation->getId(), $donation->getDonor() ) );

		$this->sendDonationConfirmationEmail( $donation, $paymentResult->paymentComplete );
		// The notifier checks if a notification is really needed (e.g. amount too high)
		$this->notifier->sendModerationNotificationToAdmin( $donation );

		return AddDonationResponse::newSuccessResponse(
			$donation,
			$tokens->getUpdateToken(),
			$tokens->getAccessToken(),
			$this->generatePaymentProviderUrl( $paymentResult->paymentProviderURLGenerator, $donation, $tokens )
		);
	}

	private function newDonationFromRequest( AddDonationRequest $donationRequest, int $paymentId ): Donation {
		$donor = $this->getPersonalInfoFromRequest( $donationRequest );
		$this->processNewsletterAndReceiptOptions( $donationRequest, $donor );
		return new Donation(
			null,
			$donor,
			$paymentId,
			$this->newTrackingInfoFromRequest( $donationRequest )
		);
	}

	private function getPersonalInfoFromRequest( AddDonationRequest $request ): Donor {
		$donorType = $request->getDonorType();
		if ( $donorType->is( DonorType::PERSON() ) ) {

			return new PersonDonor(
				new PersonName(
					$request->getDonorFirstName(),
					$request->getDonorLastName(),
					$request->getDonorSalutation(),
					$request->getDonorTitle()
				),
				$this->getPhysicalAddressFromRequest( $request ),
				$request->getDonorEmailAddress()
			);
		} elseif ( $donorType->is( DonorType::COMPANY() ) ) {
			return new CompanyDonor(
				new CompanyName( $request->getDonorCompany() ),
				$this->getPhysicalAddressFromRequest( $request ),
				$request->getDonorEmailAddress()
			);
		} elseif ( $donorType->is( DonorType::EMAIL() ) ) {
			return new Donor\EmailDonor(
				new PersonName(
					$request->getDonorFirstName(),
					$request->getDonorLastName(),
					$request->getDonorSalutation(),
					$request->getDonorTitle()
				),
				$request->getDonorEmailAddress()
			);
		} elseif ( $donorType->is( DonorType::ANONYMOUS() ) ) {
			return new AnonymousDonor();
		}
		throw new \InvalidArgumentException( sprintf( 'Unknown donor type: %s', $request->getDonorType() ) );
	}

	private function getPhysicalAddressFromRequest( AddDonationRequest $request ): PostalAddress {
		return new PostalAddress(
			$request->getDonorStreetAddress(),
			$request->getDonorPostalCode(),
			$request->getDonorCity(),
			$request->getDonorCountryCode()
		);
	}

	private function newTrackingInfoFromRequest( AddDonationRequest $request ): DonationTrackingInfo {
		$trackingInfo = DonationTrackingInfo::newBlankTrackingInfo();

		$trackingInfo->setTracking( $request->getTracking() );
		$trackingInfo->setTotalImpressionCount( $request->getTotalImpressionCount() );
		$trackingInfo->setSingleBannerImpressionCount( $request->getSingleBannerImpressionCount() );

		return $trackingInfo->freeze()->assertNoNullFields();
	}

	private function sendDonationConfirmationEmail( Donation $donation, bool $paymentIsComplete ): void {
		if ( $donation->getDonor()->hasEmailAddress() && $paymentIsComplete ) {
			$this->notifier->sendConfirmationFor( $donation );
		}
	}

	/**
	 * Modify PaymentCreationRequest from the AddDonationRequest
	 *
	 * We need to add donor-type specific properties (bank transfer code and validation)
	 * to the original request. PaymentCreationRequest is immutable so we create a new one.
	 *
	 * @param AddDonationRequest $request
	 * @return PaymentCreationRequest
	 */
	private function getPaymentRequestForDonor( AddDonationRequest $request ): PaymentCreationRequest {
		$paymentRequest = $request->getPaymentCreationRequest();
		$paymentReferenceCodePrefix = self::PREFIX_BANK_TRANSACTION_KNOWN_DONOR;
		if ( $request->donorIsAnonymous() ) {
			$paymentReferenceCodePrefix = self::PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR;
		}

		$newPaymentCreationRequest = new PaymentCreationRequest(
			$paymentRequest->amountInEuroCents,
			$paymentRequest->interval,
			$paymentRequest->paymentType,
			$paymentRequest->iban,
			$paymentRequest->bic,
			$paymentReferenceCodePrefix
		);
		$newPaymentCreationRequest->setDomainSpecificPaymentValidator(
			$this->paymentService->createPaymentValidator( $request->getDonorType() )
		);
		return $newPaymentCreationRequest;
	}

	private function generatePaymentProviderUrl( PaymentProviderURLGenerator $paymentProviderURLGenerator, Donation $donation, DonationTokens $tokens ): string {
		$name = $donation->getDonor()->getName()->toArray();
		return $paymentProviderURLGenerator->generateURL( new RequestContext(
			$donation->getId(),
			$this->generatePayPalInvoiceId( $donation ),
			$tokens->getUpdateToken(),
			$tokens->getAccessToken(),
			$name['firstName'] ?? '',
			$name['lastName'] ?? '',
		) );
	}

	/**
	 * We use the donation primary key as the InvoiceId because they're unique
	 * But we prepend a letter to make sure they don't clash with memberships
	 *
	 * @param Donation $donation
	 * @return string
	 */
	private function generatePayPalInvoiceId( Donation $donation ): string {
		return 'D' . $donation->getId();
	}

	private function processNewsletterAndReceiptOptions( AddDonationRequest $donationRequest, Donor $donor ): void {
		if ( $donationRequest->getOptsIntoDonationReceipt() ) {
			$donor->requireReceipt();
		} else {
			$donor->declineReceipt();
		}

		if ( $donationRequest->getOptsIntoNewsletter() ) {
			$donor->subscribeToNewsletter();
		} else {
			$donor->unsubscribeFromNewsletter();
		}
	}

}
