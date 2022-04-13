<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentReferenceCode;
use WMDE\Fundraising\PaymentContext\Domain\PaymentReferenceCodeGenerator;

/**
 * @license GPL-2.0-or-later
 */
class UniqueTransferCodeGenerator extends PaymentReferenceCodeGenerator {

	private PaymentReferenceCodeGenerator $generator;
	private EntityRepository $entityRepository;

	public function __construct( PaymentReferenceCodeGenerator $generator, EntityManager $entityManager ) {
		parent::__construct();
		$this->generator = $generator;
		// TODO No longer a valid dependency, pass in the db connection instead
		$this->entityRepository = $entityManager->getRepository( Donation::class );
	}

	public function newPaymentReference( string $prefix ): PaymentReferenceCode {
		do {
			$transferCode = $this->generator->newPaymentReference( $prefix );
		} while ( $this->codeIsNotUnique( $transferCode ) );

		return $transferCode;
	}

	private function codeIsNotUnique( PaymentReferenceCode $transferCode ): bool {
		// TODO This will no longer work - use a SQL UNION statement instead of repo, code must be unique across payments
		return !empty( $this->entityRepository->findBy( [ 'bankTransferCode' => $transferCode ] ) );
	}

	protected function getNextCharacterIndex(): int {
		return $this->generator->getNextCharacterIndex();
	}

}
