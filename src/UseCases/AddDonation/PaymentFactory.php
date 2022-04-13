<?php
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddDonation;

use WMDE\Fundraising\DonationContext\DummyPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\Payment;
use WMDE\Fundraising\PaymentContext\Domain\PaymentReferenceCodeGenerator;

class PaymentFactory {
	private const PREFIX_BANK_TRANSACTION_KNOWN_DONOR = 'XW';
	private const PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR = 'XR';

	private PaymentReferenceCodeGenerator $transferCodeGenerator;

	public function __construct( PaymentReferenceCodeGenerator $transferCodeGenerator ) {
		$this->transferCodeGenerator = $transferCodeGenerator;
	}

	public function getPaymentFromRequest( AddDonationRequest $donationRequest ): Payment {
		// TODO use "create payment use case", using transfer code prefix method
		return DummyPayment::create();
	}

	private function getTransferCodePrefix( AddDonationRequest $request ): string {
		if ( $request->donorIsAnonymous() ) {
			return self::PREFIX_BANK_TRANSACTION_ANONYMOUS_DONOR;
		}
		return self::PREFIX_BANK_TRANSACTION_KNOWN_DONOR;
	}
}
