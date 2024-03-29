<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration;

use PHPUnit\Framework\Assert;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\DonationEventLoggerSpy;

trait DonationEventLoggerAsserter {
	public function assertEventLogContainsExpression( DonationEventLoggerSpy $eventLoggerSpy, int $donationId, string $expr ): void {
		Assert::assertCount(
			1,
			array_filter(
				$eventLoggerSpy->getLogCalls(),
				static function ( $call ) use ( $donationId, $expr ) {
					return $call[0] == $donationId && preg_match( $expr, $call[1] );
				}
			),
			'Failed to assert that donation event log contained "' . $expr . '" for donation id ' . $donationId
		);
	}
}
