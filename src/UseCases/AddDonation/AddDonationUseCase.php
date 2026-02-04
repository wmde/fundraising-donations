<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Address\PostalAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\AnonymousDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\CompanyDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\CompanyContactName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\PersonName;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\PersonDonor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorType;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationIdRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\EventEmitter;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\UrlGenerator\DomainSpecificContext;
use WMDE\Fundraising\PaymentContext\Services\URLAuthenticator;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentParameters;
use WMDE\FunValidators\ConstraintViolation;

class AddDonationUseCase {

	private const PREFIX_BANK_TRANSACTION_KNOWN_DONOR = 'XW';
	private const PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR = 'XR';

	public function __construct(
		private readonly DonationIdRepository $idGenerator,
		private readonly DonationRepository $donationRepository,
		private readonly AddDonationValidator $donationValidator,
		private readonly ModerationService $policyValidator,
		private readonly DonationNotifier $notifier,
		private readonly DonationAuthorizer $donationAuthorizer,
		private readonly EventEmitter $eventEmitter,
		private readonly CreatePaymentService $paymentService
	) {
	}

	public function addDonation( AddDonationRequest $donationRequest ): AddDonationResponse {
		$validationResult = $this->donationValidator->validate( $donationRequest );
		if ( $validationResult->hasViolations() ) {
			return AddDonationResponse::newFailureResponse( $validationResult->getViolations() );
		}

		$donationId = $this->idGenerator->getNewId();
		$urlAuthenticator = $this->donationAuthorizer->authorizeDonationAccess( $donationId );
		$paymentRequest = $this->getPaymentRequestForDonor( $donationRequest, $donationId, $urlAuthenticator );
		$paymentResult = $this->paymentService->createPayment( $paymentRequest );
		if ( $paymentResult instanceof FailureResponse ) {
			return AddDonationResponse::newFailureResponse( [
				new ConstraintViolation( $donationRequest->getPaymentParameters(), $paymentResult->errorMessage, 'payment' )
			] );
		}

		$donation = $this->newDonationFromRequest( $donationRequest, $donationId, $paymentResult->paymentId );

		$moderationResult = $this->policyValidator->moderateDonationRequest( $donationRequest );
		if ( $moderationResult->needsModeration() ) {
			$donation->markForModeration( ...$moderationResult->getViolations() );
		}

		$this->donationRepository->storeDonation( $donation );

		$this->eventEmitter->emit( new DonationCreatedEvent( $donation->getId(), $donation->getDonor() ) );

		$this->sendDonationConfirmationEmail( $donation, $paymentResult->paymentComplete );
		// The notifier checks if a notification is really needed (e.g. amount too high)
		$this->notifier->sendModerationNotificationToAdmin( $donation );

		return AddDonationResponse::newSuccessResponse( $donation, $paymentResult->paymentCompletionUrl );
	}

	private function newDonationFromRequest( AddDonationRequest $donationRequest, int $donationId, int $paymentId ): Donation {
		$donor = $this->getPersonalInfoFromRequest( $donationRequest );
		$this->processNewsletterAndReceiptOptions( $donationRequest, $donor );
		return new Donation(
			$donationId,
			$donor,
			$paymentId,
			$donationRequest->getTrackingInfo(),
			new \DateTimeImmutable(),
		);
	}

	private function getPersonalInfoFromRequest( AddDonationRequest $request ): Donor {
		$donorType = $request->getDonorType();

		switch ( $donorType ) {
			case DonorType::PERSON:
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
			case DonorType::COMPANY:
				return new CompanyDonor(
					new CompanyContactName(
						$request->getDonorCompany(),
						$request->getDonorFirstName(),
						$request->getDonorLastName(),
						$request->getDonorSalutation(),
						$request->getDonorTitle()
					),
					$this->getPhysicalAddressFromRequest( $request ),
					$request->getDonorEmailAddress()
				);
			case DonorType::EMAIL:
				return new Donor\EmailDonor(
					new PersonName(
						$request->getDonorFirstName(),
						$request->getDonorLastName(),
						$request->getDonorSalutation(),
						$request->getDonorTitle()
					),
					$request->getDonorEmailAddress()
				);
			case DonorType::ANONYMOUS:
				return new AnonymousDonor();
		}
	}

	private function getPhysicalAddressFromRequest( AddDonationRequest $request ): PostalAddress {
		return PostalAddress::fromLegacyStreetName(
			$request->getDonorStreetAddress(),
			$request->getDonorPostalCode(),
			$request->getDonorCity(),
			$request->getDonorCountryCode()
		);
	}

	private function sendDonationConfirmationEmail( Donation $donation, bool $paymentIsComplete ): void {
		if ( $donation->shouldSendConfirmationMail() && $paymentIsComplete ) {
			$this->notifier->sendConfirmationFor( $donation );
		}
	}

	private function getPaymentRequestForDonor( AddDonationRequest $request, int $donationId,
												URLAuthenticator $urlAuthenticator ): PaymentCreationRequest {
		$context = new DomainSpecificContext(
			$donationId,
			null,
			$this->generatePayPalInvoiceId( $donationId ),
			$request->getDonorFirstName(),
			$request->getDonorLastName()
		);
		return PaymentCreationRequest::newFromParameters(
			$this->getPaymentParametersForDonor( $request ),
			$this->paymentService->createPaymentValidator(),
			$context,
			$urlAuthenticator
		);
	}

	/**
	 * Modify PaymentParameters from the AddDonationRequest
	 *
	 * We need to add donor-type specific properties (bank transfer code)
	 * to the original request.
	 */
	private function getPaymentParametersForDonor( AddDonationRequest $request ): PaymentParameters {
		$paymentParameters = $request->getPaymentParameters();
		$paymentReferenceCodePrefix = self::PREFIX_BANK_TRANSACTION_KNOWN_DONOR;
		if ( $request->donorIsAnonymous() ) {
			$paymentReferenceCodePrefix = self::PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR;
		}
		return PaymentParameterBuilder::fromExistingParameters( $paymentParameters )
			->withPaymentReferenceCodePrefix( $paymentReferenceCodePrefix )
			->getPaymentParameters();
	}

	/**
	 * We use the donation primary key as the InvoiceId because they're unique
	 * But we prepend a letter to make sure they don't clash with memberships
	 */
	private function generatePayPalInvoiceId( int $donationId ): string {
		return 'D' . $donationId;
	}

	private function processNewsletterAndReceiptOptions( AddDonationRequest $donationRequest, Donor $donor ): void {
		if ( $donationRequest->getOptsIntoDonationReceipt() ) {
			$donor->requireReceipt();
		} else {
			$donor->declineReceipt();
		}

		if ( $donationRequest->getOptsIntoNewsletter() ) {
			$donor->subscribeToMailingList();
		} else {
			$donor->unsubscribeFromMailingList();
		}
	}

}
