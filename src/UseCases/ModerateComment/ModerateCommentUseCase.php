<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\ModerateComment;

use WMDE\Fundraising\DonationContext\Domain\Repositories\CommentRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Infrastructure\DonationEventLogger;

class ModerateCommentUseCase {

	private const PUBLISH_LOG_MESSAGE = 'Comment published by user: %s';
	private const RETRACT_LOG_MESSAGE = 'Comment set to private by user: %s';

	private CommentRepository $repository;
	private DonationEventLogger $donationEventLogger;

	public function __construct( CommentRepository $repository, DonationEventLogger $donationEventLogger ) {
		$this->repository = $repository;
		$this->donationEventLogger = $donationEventLogger;
	}

	/**
	 * @param ModerateCommentRequest $moderateCommentRequest
	 *
	 * @return ModerateCommentErrorResponse|ModerateCommentSuccessResponse
	 */
	public function moderateComment( ModerateCommentRequest $moderateCommentRequest ): ModerateCommentResponse {
		try {
			$comment = $this->repository->getCommentByDonationId( $moderateCommentRequest->getDonationId() );
		} catch ( GetDonationException $e ) {
			return new ModerateCommentErrorResponse( ModerateCommentErrorResponse::ERROR_DONATION_NOT_FOUND );
		}
		if ( $comment === null ) {
			return new ModerateCommentErrorResponse( ModerateCommentErrorResponse::ERROR_DONATION_HAS_NO_COMMENT );
		}

		if ( $moderateCommentRequest->shouldPublish() ) {
			$comment->publish();
			$logMessage = self::PUBLISH_LOG_MESSAGE;
		} else {
			$comment->retract();
			$logMessage = self::RETRACT_LOG_MESSAGE;
		}
		$this->repository->updateComment( $comment );

		$this->donationEventLogger->log(
			$moderateCommentRequest->getDonationId(),
			sprintf( $logMessage, $moderateCommentRequest->getAuthorizedUser() )
		);

		return new ModerateCommentSuccessResponse( $moderateCommentRequest->getDonationId() );
	}

}
