<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;

#[CoversClass( DonationTrackingInfo::class )]
class DonationTrackingInfoTest extends TestCase {

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

	#[DataProvider( 'provideTrackingStringTestData' )]
	public function testGetTrackingString( string $campaign, string $keyword, string $expectedTrackingString ): void {
		$trackingInfo = new DonationTrackingInfo( $campaign, $keyword, 0, 0 );

		$this->assertSame( $expectedTrackingString, $trackingInfo->getTrackingString() );
	}

	/**
	 * @return iterable<array{string,string,string}>
	 */
	public static function provideTrackingStringTestData(): iterable {
		yield 'all empty' => [ '', '', '' ];
		yield 'empty campaign returns empty tracking' => [ '', 'some_keyword', '' ];
		yield 'empty keyword returns campaign' => [ 'org-16', '', 'org-16' ];
		yield 'campaign and keyword concatenated' => [ 'org-16', 'desktop-16-ctrl', 'org-16/desktop-16-ctrl' ];
		yield 'uppercase campaign becomes lowercase' => [ 'Org-16', '', 'org-16' ];
		yield 'uppercase campaign and keyword become lowercase' => [ 'Org-17', 'Desktop-17-CTRL', 'org-17/desktop-17-ctrl' ];
	}
}
