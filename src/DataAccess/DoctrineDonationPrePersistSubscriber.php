<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\DataAccess;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use WMDE\Fundraising\DonationContext\Authorization\TokenGenerator;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;

/**
 * @license GPL-2.0-or-later
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DoctrineDonationPrePersistSubscriber implements EventSubscriber {

	private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

	private $updateTokenGenerator;
	private $accessTokenGenerator;

	public function __construct( TokenGenerator $updateTokenGenerator, TokenGenerator $accessTokenGenerator ) {
		$this->updateTokenGenerator = $updateTokenGenerator;
		$this->accessTokenGenerator = $accessTokenGenerator;
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
