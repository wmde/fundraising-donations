<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\DoctrineEntities\DonationPayments;

use DateTime;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments\SofortPayment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationPayments\SofortPayment
 */
class SofortPaymentTest extends TestCase {

	public function testInitialProperties(): void {
		$payment = new SofortPayment();
		$this->assertNull( $payment->getConfirmedAt() );
	}

	public function testAccessors(): void {
		$payment = new SofortPayment();
		$payment->setId( 5 );
		$payment->setConfirmedAt( new DateTime( '2008-11-03T15:30:00Z' ) );

		$this->assertSame( 5, $payment->getId() );
		$this->assertEquals( new DateTime( '2008-11-03T15:30:00Z' ), $payment->getConfirmedAt() );
	}
}
