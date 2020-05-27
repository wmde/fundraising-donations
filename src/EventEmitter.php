<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use WMDE\Fundraising\DonationContext\Domain\Event;

interface EventEmitter {
	public function emit( Event $event ): void;
}