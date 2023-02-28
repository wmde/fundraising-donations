<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;

class DoctrineDonationPrePersistSubscriber implements EventSubscriber {

	private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	public function __construct(
		private readonly TokenGenerator $updateTokenGenerator,
		private readonly TokenGenerator $accessTokenGenerator ) {
	}

	public function getSubscribedEvents(): array {
		return [ Events::prePersist ];
	}

	public function prePersist( LifecycleEventArgs $args ): void {
		$entity = $args->getObject();

		if ( $entity instanceof Donation ) {
			$entity->modifyDataObject(
				function ( DonationData $data ): void {
					if ( $this->isEmpty( $data->getAccessToken() ) ) {
						$data->setAccessToken( $this->accessTokenGenerator->generateToken() );
					}

					if ( $this->isEmpty( $data->getUpdateToken() ) ) {
						$data->setUpdateToken( $this->updateTokenGenerator->generateToken() );
					}

					if ( $this->isEmpty( $data->getUpdateTokenExpiry() ) ) {
						$expiry = $this->updateTokenGenerator->generateTokenExpiry();
						$data->setUpdateTokenExpiry( $expiry->format( self::DATE_TIME_FORMAT ) );
					}
				}
			);
		}
	}

	private function isEmpty( ?string $stringOrNull ): bool {
		return $stringOrNull === null || $stringOrNull === '';
	}

}
