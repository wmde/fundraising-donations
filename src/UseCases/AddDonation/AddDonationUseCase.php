<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetcher;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationPayment;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorAddress;
use WMDE\Fundraising\DonationContext\Domain\Model\DonorName;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
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
 * @license GNU GPL v2+
 */
class AddDonationUseCase {

	const PREFIX_BANK_TRANSACTION_KNOWN_DONOR = 'XW';
	const PREFIX_BANK_TRANSACTION_ANONYNMOUS_DONOR = 'XR';

	private $donationRepository;
	private $donationValidator;
	private $policyValidator;
	private $referrerGeneralizer;
	private $mailer;
	private $transferCodeGenerator;
	private $tokenFetcher;
	private $initialDonationStatusPicker;

	public function __construct( DonationRepository $donationRepository, AddDonationValidator $donationValidator,
		AddDonationPolicyValidator $policyValidator, ReferrerGeneralizer $referrerGeneralizer,
		DonationConfirmationMailer $mailer, TransferCodeGenerator $transferCodeGenerator,
		DonationTokenFetcher $tokenFetcher, InitialDonationStatusPicker $initialDonationStatusPicker ) {
		$this->donationRepository = $donationRepository;
		$this->donationValidator = $donationValidator;
		$this->policyValidator = $policyValidator;
		$this->referrerGeneralizer = $referrerGeneralizer;
		$this->mailer = $mailer;
		$this->transferCodeGenerator = $transferCodeGenerator;
		$this->tokenFetcher = $tokenFetcher;
		$this->initialDonationStatusPicker = $initialDonationStatusPicker;
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
			$donation->markAsDeleted();
		}

		// TODO: handle exceptions
		$this->donationRepository->storeDonation( $donation );

		// TODO: handle exceptions
		$tokens = $this->tokenFetcher->getTokens( $donation->getId() );

		// TODO: handle exceptions
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

	private function getPersonalInfoFromRequest( AddDonationRequest $request ): ?Donor {
		if ( $request->donorIsAnonymous() ) {
			return null;
		}
		return new Donor(
			$this->getNameFromRequest( $request ),
			$this->getPhysicalAddressFromRequest( $request ),
			$request->getDonorEmailAddress()
		);
	}

	private function getPhysicalAddressFromRequest( AddDonationRequest $request ): DonorAddress {
		$address = new DonorAddress();

		$address->setStreetAddress( $request->getDonorStreetAddress() );
		$address->setPostalCode( $request->getDonorPostalCode() );
		$address->setCity( $request->getDonorCity() );
		$address->setCountryCode( $request->getDonorCountryCode() );

		return $address->freeze()->assertNoNullFields();
	}

	private function getNameFromRequest( AddDonationRequest $request ): DonorName {
		$name = $request->donorIsCompany() ? DonorName::newCompanyName() : DonorName::newPrivatePersonName();

		$name->setSalutation( $request->getDonorSalutation() );
		$name->setTitle( $request->getDonorTitle() );
		$name->setCompanyName( $request->getDonorCompany() );
		$name->setFirstName( $request->getDonorFirstName() );
		$name->setLastName( $request->getDonorLastName() );

		return $name->freeze()->assertNoNullFields();
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
						$this->getTransferCodePrefixForDonorType( $donationRequest->getDonorType() )
					)
				);
			case PaymentMethod::DIRECT_DEBIT:
				return new DirectDebitPayment( $donationRequest->getBankData() );
			case PaymentMethod::PAYPAL:
				return new PayPalPayment( new PayPalData() );
			case PaymentMethod::SOFORT:
				return new SofortPayment(
					$this->transferCodeGenerator->generateTransferCode(
						$this->getTransferCodePrefixForDonorType( $donationRequest->getDonorType() )
					)
				);
			default:
				return new PaymentWithoutAssociatedData( $paymentType );
		}
	}

	private function getTransferCodePrefixForDonorType( string $donorType ): string {
		if ( $donorType === DonorName::PERSON_PRIVATE || $donorType === DonorName::PERSON_COMPANY ) {
			return self::PREFIX_BANK_TRANSACTION_KNOWN_DONOR;
		}
		return self::PREFIX_BANK_TRANSACTION_ANONYNMOUS_DONOR;
	}

	private function newTrackingInfoFromRequest( AddDonationRequest $request ): DonationTrackingInfo {
		$trackingInfo = new DonationTrackingInfo();

		$trackingInfo->setTracking( $request->getTracking() );
		$trackingInfo->setSource( $this->referrerGeneralizer->generalize( $request->getSource() ) );
		$trackingInfo->setTotalImpressionCount( $request->getTotalImpressionCount() );
		$trackingInfo->setSingleBannerImpressionCount( $request->getSingleBannerImpressionCount() );
		$trackingInfo->setColor( $request->getColor() );
		$trackingInfo->setSkin( $request->getSkin() );
		$trackingInfo->setLayout( $request->getLayout() );

		return $trackingInfo->freeze()->assertNoNullFields();
	}

	/**
	 * @param Donation $donation
	 *
	 * @throws \RuntimeException
	 */
	private function sendDonationConfirmationEmail( Donation $donation ): void {
		if ( $donation->getDonor() !== null && !$donation->hasExternalPayment() ) {
			$this->mailer->sendConfirmationMailFor( $donation );
		}
	}

}