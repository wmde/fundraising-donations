<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Fixtures;

use WMDE\Fundraising\DonationContext\Domain\Event;
use WMDE\Fundraising\DonationContext\EventEmitter;

class EventEmitterSpy implements EventEmitter {
	private array $events;

	public function emit( Event $event ): void {
		$this->events[] = $event;
	}

	/**
	 * @return Event[]
	 */
	public function getEvents(): array {
		return $this->events;
	}

}