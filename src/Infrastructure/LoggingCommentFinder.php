<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use WMDE\Fundraising\DonationContext\Domain\ReadModel\Comment;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentFinder;
use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentListingException;

class LoggingCommentFinder implements CommentFinder {

	private const CONTEXT_EXCEPTION_KEY = 'exception';

	private string $logLevel;

	public function __construct(
		private readonly CommentFinder $commentFinder,
		private readonly LoggerInterface $logger
	) {
		$this->logLevel = LogLevel::CRITICAL;
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 *
	 * @return Comment[]
	 * @throws CommentListingException
	 * @see CommentFinder::getPublicComments
	 */
	public function getPublicComments( int $limit, int $offset = 0 ): array {
		try {
			return $this->commentFinder->getPublicComments( $limit, $offset );
		} catch ( CommentListingException $ex ) {
			$this->logger->log( $this->logLevel, $ex->getMessage(), [ self::CONTEXT_EXCEPTION_KEY => $ex ] );
			throw $ex;
		}
	}
}
