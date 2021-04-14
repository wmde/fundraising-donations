<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Domain\Event\DonationCreatedEvent;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationPayment;
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
use WMDE\Fundraising\DonationContext\Infrastructure\DonationConfirmationMailer;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentWithoutAssociatedData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Domain\TransferCodeGenerator;

/**
 * @license GPL-2.0-or-later
 */
class AddDonationUseCase {

	private const PREFIX_BANK_TRANSACTION_KNOWN_DONOR = 'XW';
	private const PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR = 'XR';

	private DonationRepository $donationRepository;
	private AddDonationValidator $donationValidator;
	private AddDonationPolicyValidator $policyValidator;
	private DonationConfirmationMailer $mailer;
	private TransferCodeGenerator $transferCodeGenerator;
	private DonationTokenFetcher $tokenFetcher;
	private InitialDonationStatusPicker $initialDonationStatusPicker;
	private EventEmitter $eventEmitter;

	public function __construct( DonationRepository $donationRepository, AddDonationValidator $donationValidator,
			AddDonationPolicyValidator $policyValidator, DonationConfirmationMailer $mailer,
			TransferCodeGenerator $transferCodeGenerator, DonationTokenFetcher $tokenFetcher,
			InitialDonationStatusPicker $initialDonationStatusPicker, EventEmitter $eventEmitter ) {
		$this->donationRepository = $donationRepository;
		$this->donationValidator = $donationValidator;
		$this->policyValidator = $policyValidator;
		$this->mailer = $mailer;
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

		if ( $this->policyValidator->needsModeration( $donationRequest ) ) {
			$donation->notifyOfPolicyValidationFailure();
		}

		if ( $this->policyValidator->isAutoDeleted( $donationRequest ) ) {
			$donation->cancelWithoutChecks();
		}

		$this->donationRepository->storeDonation( $donation );

		$tokens = $this->tokenFetcher->getTokens( $donation->getId() );

		$this->eventEmitter->emit( new DonationCreatedEvent( $donation->getId(), $donation->getDonor() ) );

		$this->sendDonationConfirmationEmail( $donation );

		return AddDonationResponse::newSuccessResponse(
			$donation,
			$tokens->getUpdateToken(),
			$tokens->getAccessToken()
		);
	}

	private function newDonationFromRequest( AddDonationRequest $donationRequest ): Donation {
		$donation = new Donation(
			null,
			( $this->initialDonationStatusPicker )( $donationRequest->getPaymentType() ),
			$this->getPersonalInfoFromRequest( $donationRequest ),
			$this->getPaymentFromRequest( $donationRequest ),
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

	private function getPaymentFromRequest( AddDonationRequest $donationRequest ): DonationPayment {
		return new DonationPayment(
			$donationRequest->getAmount(),
			$donationRequest->getInterval(),
			$this->getPaymentMethodFromRequest( $donationRequest )
		);
	}

	private function getPaymentMethodFromRequest( AddDonationRequest $donationRequest ): PaymentMethod {
		$paymentType = $donationRequest->getPaymentType();

		switch ( $paymentType ) {
			case PaymentMethod::BANK_TRANSFER:
				return new BankTransferPayment(
					$this->transferCodeGenerator->generateTransferCode(
						$this->getTransferCodePrefix( $donationRequest )
					)
				);
			case PaymentMethod::DIRECT_DEBIT:
				return new DirectDebitPayment( $donationRequest->getBankData() );
			case PaymentMethod::PAYPAL:
				return new PayPalPayment( new PayPalData() );
			case PaymentMethod::SOFORT:
				return new SofortPayment(
					$this->transferCodeGenerator->generateTransferCode(
						$this->getTransferCodePrefix( $donationRequest )
					)
				);
			default:
				return new PaymentWithoutAssociatedData( $paymentType );
		}
	}

	private function getTransferCodePrefix( AddDonationRequest $request ): string {
		if ( $request->donorIsAnonymous() ) {
			return self::PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR;
		}
		return self::PREFIX_BANK_TRANSACTION_KNOWN_DONOR;
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
			$this->mailer->sendConfirmationMailFor( $donation );
		}
	}

}
