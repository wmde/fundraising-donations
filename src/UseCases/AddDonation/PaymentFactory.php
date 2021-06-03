<?php
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\Domain\Model\DonationPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\DirectDebitPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalData;
use WMDE\Fundraising\PaymentContext\Domain\Model\PayPalPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;
use WMDE\Fundraising\PaymentContext\Domain\TransferCodeGenerator;

class PaymentFactory {
	private const PREFIX_BANK_TRANSACTION_KNOWN_DONOR = 'XW';
	private const PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR = 'XR';

	private TransferCodeGenerator $transferCodeGenerator;

	public function __construct( TransferCodeGenerator $transferCodeGenerator ) {
		$this->transferCodeGenerator = $transferCodeGenerator;
	}

	public function getPaymentFromRequest( AddDonationRequest $donationRequest ): DonationPayment {
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
				return new BankTransferPayment( $this->getTransferCode( $donationRequest ) );
			case PaymentMethod::DIRECT_DEBIT:
				return new DirectDebitPayment( $donationRequest->getBankData() );
			case PaymentMethod::PAYPAL:
				return new PayPalPayment( new PayPalData() );
			case PaymentMethod::CREDIT_CARD:
				return new CreditCardPayment();
			case PaymentMethod::SOFORT:
				return new SofortPayment( $this->getTransferCode( $donationRequest ) );
			default:
				throw new \UnexpectedValueException( sprintf( 'Unknown Payment type: %s', $paymentType ) );
		}
	}

	private function getTransferCode( AddDonationRequest $request ): string {
		return $this->transferCodeGenerator->generateTransferCode(
			$this->getTransferCodePrefix( $request )
		);
	}

	private function getTransferCodePrefix( AddDonationRequest $request ): string {
		if ( $request->donorIsAnonymous() ) {
			return self::PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR;
		}
		return self::PREFIX_BANK_TRANSACTION_KNOWN_DONOR;
	}
}
