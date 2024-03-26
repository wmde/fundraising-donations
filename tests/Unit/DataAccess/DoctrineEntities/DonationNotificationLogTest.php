<?php

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess\DoctrineEntities;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\DonationNotificationLog;

#[CoversClass( DonationNotificationLog::class )]
class DonationNotificationLogTest extends TestCase {

	public function testDonationIdGetsSet(): void {
		$donationId = 5;
		$donationNotificationLog = new DonationNotificationLog( $donationId );

		$this->assertSame( $donationId, $donationNotificationLog->getDonationId() );
	}

}
