<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
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
use WMDE\Fundraising\DonationContext\RefactoringException;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\FailureResponse;
use WMDE\Fundraising\PaymentContext\UseCases\CreatePayment\PaymentCreationRequest;

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
			throw new RefactoringException( 'TODO: Implement returning error response with the violations from $paymentResult' );
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

		$this->sendDonationConfirmationEmail( $donation );
		// The notifier checks if a notification is really needed (e.g. amount too high)
		$this->notifier->sendModerationNotificationToAdmin( $donation );

		return AddDonationResponse::newSuccessResponse(
			$donation,
			$tokens->getUpdateToken(),
			$tokens->getAccessToken()
		);
	}

	private function newDonationFromRequest( AddDonationRequest $donationRequest, int $paymentId ): Donation {
		$donation = new Donation(
			null,
			$this->getPersonalInfoFromRequest( $donationRequest ),
			$paymentId,
			$donationRequest->getOptIn() === '1',
			$this->newTrackingInfoFromRequest( $donationRequest )
		);
		$donation->setOptsIntoDonationReceipt( $donationRequest->getOptsIntoDonationReceipt() );

		return $donation;
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

	private function sendDonationConfirmationEmail( Donation $donation ): void {
		// TODO change logic - Check payment response if payment has completed instead of checking donation
		if ( $donation->getDonor()->hasEmailAddress() && !$donation->hasBookablePayment() ) {
			$this->notifier->sendConfirmationFor( $donation );
		}
	}

	private function getPaymentRequestForDonor( AddDonationRequest $request ): PaymentCreationRequest {
		$paymentRequest = $request->getPaymentCreationRequest();
		$paymentReferenceCodePrefix = self::PREFIX_BANK_TRANSACTION_KNOWN_DONOR;
		if ( $request->donorIsAnonymous() ) {
			$paymentReferenceCodePrefix = self::PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR;
		}

		return new PaymentCreationRequest(
			$paymentRequest->amountInEuroCents,
			$paymentRequest->interval,
			$paymentRequest->paymentType,
			$paymentRequest->iban,
			$paymentRequest->bic,
			$paymentReferenceCodePrefix
		);
	}

}
