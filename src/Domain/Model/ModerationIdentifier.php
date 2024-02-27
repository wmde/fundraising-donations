<?php
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

enum ModerationIdentifier: string {
	case AMOUNT_TOO_HIGH = 'AMOUNT_TOO_HIGH';
	case ADDRESS_CONTENT_VIOLATION = 'ADDRESS_CONTENT_VIOLATION';
	case COMMENT_CONTENT_VIOLATION = 'COMMENT_CONTENT_VIOLATION';
	case MANUALLY_FLAGGED_BY_ADMIN = 'MANUALLY_FLAGGED_BY_ADMIN';
	case EMAIL_BLOCKED = 'EMAIL_BLOCKED';
}
