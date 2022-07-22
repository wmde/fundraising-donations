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
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\Moderation\ModerationService;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;
use WMDE\Fundraising\PaymentContext\Domain\TransferCodeGenerator;

/**
 * @license GPL-2.0-or-later
 */
class AddDonationUseCase {

	private DonationRepository $donationRepository;
	private AddDonationValidator $donationValidator;
	private ModerationService $policyValidator;
	private DonationNotifier $notifier;
	private TransferCodeGenerator $transferCodeGenerator;
	private DonationTokenFetcher $tokenFetcher;
	private InitialDonationStatusPicker $initialDonationStatusPicker;
	private EventEmitter $eventEmitter;

	public function __construct( DonationRepository $donationRepository, AddDonationValidator $donationValidator,
								ModerationService $policyValidator, DonationNotifier $notifier,
								TransferCodeGenerator $transferCodeGenerator, DonationTokenFetcher $tokenFetcher,
								InitialDonationStatusPicker $initialDonationStatusPicker, EventEmitter $eventEmitter ) {
		$this->donationRepository = $donationRepository;
		$this->donationValidator = $donationValidator;
		$this->policyValidator = $policyValidator;
		$this->notifier = $notifier;
		$this->transferCodeGenerator = $transferCodeGenerator;
		$this->tokenFetcher = $tokenFetcher;
		$this->initialDonationStatusPicker = $initialDonationStatusPicker;
		$this->eventEmitter = $eventEmitter;
	}

	public function addDonation( AddDonationRequest $donationRequest ): AddDonationResponse {
		$validationResult = $this->donationValidator->validate( $donationRequest );

		if ( $validationResult->hasViolations() ) {
			return AddDonationResponse::newFailureResponse( $validationResult->getViolations() );
		}

		$donation = $this->newDonationFromRequest( $donationRequest );

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

	private function newDonationFromRequest( AddDonationRequest $donationRequest ): Donation {
		$paymentFactory = new PaymentFactory( $this->transferCodeGenerator );
		$donation = new Donation(
			null,
			( $this->initialDonationStatusPicker )( $donationRequest->getPaymentType() ),
			$this->getPersonalInfoFromRequest( $donationRequest ),
			$paymentFactory->getPaymentFromRequest( $donationRequest ),
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
		if ( $donation->getDonor()->hasEmailAddress() && !$donation->hasExternalPayment() ) {
			$this->notifier->sendConfirmationFor( $donation );
		}
	}

}
