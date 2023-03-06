<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class DonationEventLoggerSpy implements DonationEventLogger {

	/**
	 * @var array<array{int, string}>
	 */
	private array $logCalls = [];

	public function log( int $donationId, string $message ): void {
		$this->logCalls[] = [ $donationId, $message ];
	}

	/**
	 * @return array<array{int, string}>
	 */
	public function getLogCalls(): array {
		return $this->logCalls;
	}

}
