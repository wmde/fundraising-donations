<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

enum ModerationIdentifier {
	case AMOUNT_TOO_HIGH;
	case ADDRESS_CONTENT_VIOLATION;
	case COMMENT_CONTENT_VIOLATION;
	case MANUALLY_FLAGGED_BY_ADMIN;
}