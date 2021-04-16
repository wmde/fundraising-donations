<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\UseCases\AddComment;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationComment;
use WMDE\Fundraising\DonationContext\Domain\Model\Donor\Name\NoName;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;
use WMDE\FunValidators\Validators\TextPolicyValidator;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class AddCommentUseCase {

	private $donationRepository;
	private $authorizationService;
	private $textPolicyValidator;
	private $commentValidator;

	public function __construct( DonationRepository $repository, DonationAuthorizer $authorizationService,
		TextPolicyValidator $textPolicyValidator, AddCommentValidator $commentValidator ) {
		$this->donationRepository = $repository;
		$this->authorizationService = $authorizationService;
		$this->textPolicyValidator = $textPolicyValidator;
		$this->commentValidator = $commentValidator;
	}

	public function addComment( AddCommentRequest $addCommentRequest ): AddCommentResponse {
		if ( !$this->requestIsAllowed( $addCommentRequest ) ) {
			return AddCommentResponse::newFailureResponse( 'comment_failure_access_denied' );
		}

		$validationResult = $this->commentValidator->validate( $addCommentRequest );
		if ( !$validationResult->isSuccessful() ) {
			return AddCommentResponse::newFailureResponse( $validationResult->getFirstViolation() );
		}

		try {
			$donation = $this->donationRepository->getDonationById( $addCommentRequest->getDonationId() );
		}
		catch ( GetDonationException $ex ) {
			return AddCommentResponse::newFailureResponse( 'comment_failure_donation_error' );
		}

		if ( $donation === null || $donation->isCancelled() ) {
			return AddCommentResponse::newFailureResponse( 'comment_failure_donation_not_found' );
		}

		if ( $donation->getComment() !== null ) {
			return AddCommentResponse::newFailureResponse( 'comment_failure_donation_has_comment' );
		}

		$successMessage = 'comment_success_ok';
		if ( $donation->isMarkedForModeration() ) {
			$successMessage = 'comment_success_needs_moderation';
		}

		$donation->addComment( $this->newComment( $donation, $addCommentRequest ) );

		if ( !$this->commentTextPassesValidation( $addCommentRequest->getCommentText() ) ) {
			$donation->notifyOfCommentValidationFailure();
			$successMessage = 'comment_success_needs_moderation';
		}

		try {
			$this->donationRepository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			return AddCommentResponse::newFailureResponse( 'comment_failure_save_error' );
		}

		return AddCommentResponse::newSuccessResponse( $successMessage );
	}

	private function requestIsAllowed( AddCommentRequest $addCommentRequest ): bool {
		return $this->authorizationService->userCanModifyDonation( $addCommentRequest->getDonationId() );
	}

	private function newComment( Donation $donation, AddCommentRequest $request ): DonationComment {
		$authorName = $donation->getDonor()->getName()->getFullName();
		if ( $request->isAnonymous() ) {
			$authorName = ( new NoName() )->getFullName();
		}
		return new DonationComment(
			$request->getCommentText(),
			$this->commentCanBePublic( $request ),
			$authorName
		);
	}

	private function commentCanBePublic( AddCommentRequest $request ): bool {
		return $request->isPublic()
			&& $this->commentTextPassesValidation( $request->getCommentText() );
	}

	private function commentTextPassesValidation( string $text ): bool {
		return $this->textPolicyValidator->textIsHarmless( $text );
	}

}
