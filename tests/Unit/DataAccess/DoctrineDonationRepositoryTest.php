<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankTransferPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\CreditCardPayment;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\Fundraising\PaymentContext\Domain\Model\SofortPayment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationRepository
 */
class DoctrineDonationRepositoryTest extends TestCase {

	/**
	 * @dataProvider getPaymentMethodsAndTransferCodes
	 */
	public function testGetBankTransferCode_identifierIsReturned( string $expectedOutput, PaymentMethod $payment ): void {
		$this->assertEquals( $expectedOutput, DoctrineDonationRepository::getBankTransferCode( $payment ) );
	}

	public function getPaymentMethodsAndTransferCodes(): array {
		return [
			[ 'ffg', new SofortPayment( 'ffg' ) ],
			[ 'hhi', new BankTransferPayment( 'hhi' ) ],
			[ '', new CreditCardPayment() ],
		];
	}
}
