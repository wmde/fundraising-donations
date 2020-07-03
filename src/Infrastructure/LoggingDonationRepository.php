<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Infrastructure;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use WMDE\Fundraising\DonationContext\Domain\Model\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Domain\Repositories\StoreDonationException;

/**
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class LoggingDonationRepository implements DonationRepository {

	private const CONTEXT_EXCEPTION_KEY = 'exception';

	private $repository;
	private $logger;
	private $logLevel;

	public function __construct( DonationRepository $repository, LoggerInterface $logger ) {
		$this->repository = $repository;
		$this->logger = $logger;
		$this->logLevel = LogLevel::CRITICAL;
	}

	/**
	 * @see DonationRepository::storeDonation
	 *
	 * @param Donation $donation
	 *
	 * @throws StoreDonationException
	 */
	public function storeDonation( Donation $donation ): void {
		try {
			$this->repository->storeDonation( $donation );
		}
		catch ( StoreDonationException $ex ) {
			$this->logger->log( $this->logLevel, $ex->getMessage(), [ self::CONTEXT_EXCEPTION_KEY => $ex ] );
			throw $ex;
		}
	}

	/**
	 * @see DonationRepository::getDonationById
	 *
	 * @param int $id
	 *
	 * @return Donation|null
	 * @throws GetDonationException
	 */
	public function getDonationById( int $id ): ?Donation {
		try {
			return $this->repository->getDonationById( $id );
		}
		catch ( GetDonationException $ex ) {
			$this->logger->log( $this->logLevel, $ex->getMessage(), [ self::CONTEXT_EXCEPTION_KEY => $ex ] );
			throw $ex;
		}
	}

}
