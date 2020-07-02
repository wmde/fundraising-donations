<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Event;
use WMDE\Fundraising\DonationContext\EventEmitter;

class FakeEventEmitter implements EventEmitter {

	public function emit( Event $event ): void {
		// Nothing happens
	}
}
