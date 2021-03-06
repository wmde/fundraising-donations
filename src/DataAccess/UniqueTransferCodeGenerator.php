<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\PaymentContext\Domain\TransferCodeGenerator;

/**
 * @license GPL-2.0-or-later
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class UniqueTransferCodeGenerator implements TransferCodeGenerator {

	private $generator;
	private $entityRepository;

	public function __construct( TransferCodeGenerator $generator, EntityManager $entityManager ) {
		$this->generator = $generator;
		$this->entityRepository = $entityManager->getRepository( Donation::class );
	}

	public function generateTransferCode( string $prefix ): string {
		do {
			$transferCode = $this->generator->generateTransferCode( $prefix );
		} while ( $this->codeIsNotUnique( $transferCode ) );

		return $transferCode;
	}

	private function codeIsNotUnique( string $transferCode ): bool {
		return !empty( $this->entityRepository->findBy( [ 'bankTransferCode' => $transferCode ] ) );
	}

}
