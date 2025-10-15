<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;

#[CoversClass( DonationTrackingInfo::class )]
class DonationTrackingInfoTest extends TestCase {
	public function testLegacyTrackingHook(): void {
		$trackingInfo = new DonationTrackingInfo( 'test_campaign', 'test_keyword', 0, 0 );
		$trackingInfoWithMissingCampaign = new DonationTrackingInfo( '', 'test_keyword', 0, 0 );

		$this->assertSame( 'test_campaign/test_keyword', $trackingInfo->tracking );
		$this->assertSame( '', $trackingInfoWithMissingCampaign->tracking );
	}

	public function testCreateWithTrackingString(): void {
		$trackingInfo = DonationTrackingInfo::newWithTrackingString( 'test_campaign/test_keyword', 5, 1 );

		$this->assertSame( 'test_campaign', $trackingInfo->campaign );
		$this->assertSame( 'test_keyword', $trackingInfo->keyword );
		$this->assertSame( 5, $trackingInfo->totalImpressionCount );
		$this->assertSame( 1, $trackingInfo->singleBannerImpressionCount );
	}

	public function testNewBlankTrackingInfo(): void {
		$trackingInfo = DonationTrackingInfo::newBlankTrackingInfo();

		$this->assertSame( '', $trackingInfo->campaign );
		$this->assertSame( '', $trackingInfo->keyword );
		$this->assertSame( 0, $trackingInfo->totalImpressionCount );
		$this->assertSame( 0, $trackingInfo->singleBannerImpressionCount );
	}

}
