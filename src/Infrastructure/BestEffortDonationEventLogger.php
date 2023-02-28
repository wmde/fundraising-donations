<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use Psr\Log\LoggerInterface;

/**
 * @license GPL-2.0-or-later
 */
class BestEffortDonationEventLogger implements DonationEventLogger {

	private DonationEventLogger $donationEventLogger;
	private LoggerInterface $logger;

	public function __construct( DonationEventLogger $donationEventLogger, LoggerInterface $logger ) {
		$this->donationEventLogger = $donationEventLogger;
		$this->logger = $logger;
	}

	public function log( int $donationId, string $message ): void {
		try {
			$this->donationEventLogger->log( $donationId, $message );
		} catch ( DonationEventLogException $e ) {
			$logContext = [
				'donationId' => $donationId,
				'exception' => $e,
				'message' => $message
			];
			$this->logger->error( 'Could not update donation event log', $logContext );
		}
	}
}
