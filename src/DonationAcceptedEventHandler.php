<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext;

use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\UseCases\DonationNotifier;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DonationAcceptedEventHandler {

	public const AUTHORIZATION_FAILED = 'Authorization failed';
	public const UNKNOWN_ID_PROVIDED = 'Unknown donation id';
	public const DATABASE_ERROR_OCCURRED = 'Database error occurred';
	public const SUCCESS = null;

	private DonationAuthorizer $authorizer;
	private DonationRepository $repository;
	private DonationNotifier $notifier;

	public function __construct( DonationAuthorizer $authorizer, DonationRepository $repository, DonationNotifier $notifier ) {
		$this->authorizer = $authorizer;
		$this->repository = $repository;
		$this->notifier = $notifier;
	}

	/**
	 * @param int $donationId
	 *
	 * @return string|null Null on success, string with error message otherwise
	 */
	public function onDonationAccepted( int $donationId ): ?string {
		if ( !$this->authorizer->systemCanModifyDonation( $donationId ) ) {
			return self::AUTHORIZATION_FAILED;
		}

		try {
			$donation = $this->repository->getDonationById( $donationId );
		}
		catch ( GetDonationException $ex ) {
			return self::DATABASE_ERROR_OCCURRED;
		}

		if ( $donation === null ) {
			return self::UNKNOWN_ID_PROVIDED;
		}

		$this->notifier->sendConfirmationFor( $donation );

		return self::SUCCESS;
	}

}
