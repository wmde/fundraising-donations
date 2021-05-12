<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities;

use Doctrine\ORM\Mapping as ORM;

/**
 * This class keeps track of donations that a notification was sent to.
 *
 * Our current system just needs a "yes/no" answer to the question if a notification was sent,
 * so we keep this class as simple as possible (no timestamps, counts, different notifications etc).
 *
 * The notification log is an implementation detail of the notification class, not part of the core domain.
 * Therefore, we only reference donations by their ID, not by any direct reference.
 *
 * @ORM\Table(name="donation_notification_log")
 * @ORM\Entity
 */
class DonationNotificationLog {

	/**
	 * This field is an integer (and not a one-to-one reference) on purpose,
	 * because the notification log is not part of the core domain
	 *
	 * @var int
	 *
	 * @ORM\Id
	 * @ORM\Column(name="donation_id", type="integer")
	 */
	private int $donationId;

	public function __construct( int $donationId ) {
		$this->donationId = $donationId;
	}

	public function getDonationId(): int {
		return $this->donationId;
	}

}
