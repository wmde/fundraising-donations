<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\DoctrineEntities;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationNotificationLog;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationNotificationLog
 */
class DonationNotificationLogTest extends TestCase {

	public function testDonationIdGetsSet(): void {
		$donationId = 5;
		$donationNotificationLog = new DonationNotificationLog( $donationId );

		$this->assertSame( $donationId, $donationNotificationLog->getDonationId() );
	}

}
