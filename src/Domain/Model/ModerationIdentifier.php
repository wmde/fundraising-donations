<?php
// phpcs:ignoreFile -- Until phpcs has 8.1 enum support, see https://github.com/squizlabs/PHP_CodeSniffer/issues/3479
declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Domain\Model;

enum ModerationIdentifier {
	case AMOUNT_TOO_HIGH;
	case ADDRESS_CONTENT_VIOLATION;
	case COMMENT_CONTENT_VIOLATION;
	case MANUALLY_FLAGGED_BY_ADMIN;
}
