<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Codeception\Specify;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationAuthorizer;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationAuthorizer
 *
 * @license GPL-2.0-or-later
 */
class DoctrineDonationAuthorizerTest extends TestCase {
	use Specify;

	private const CORRECT_UPDATE_TOKEN = 'CorrectUpdateToken';
	private const CORRECT_ACCESS_TOKEN = 'CorrectAccessToken';
	private const WRONG__UPDATE_TOKEN = 'WrongUpdateToken';
	private const WRONG_ACCESS_TOKEN = 'WrongAccessToken';
	private const MEANINGLESS_TOKEN = 'Some token';
	private const EMPTY_TOKEN = '';
	private const MEANINGLESS_DONATION_ID = 1337;
	private const ID_OF_WRONG_DONATION = 42;

	private EntityManager $entityManager;

	protected function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
	}

	private function newAuthorizationService( string $updateToken = '', string $accessToken = '' ): DonationAuthorizer {
		return new DoctrineDonationAuthorizer( $this->entityManager, $updateToken, $accessToken );
	}

	private function storeDonation( Donation $donation ) {
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();
	}

	/**
	 * @slowThreshold 400
	 */
	public function testWhenNoDonations(): void {
		$this->specify(
			'update authorization fails',
			function (): void {
				$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );
				$this->assertFalse( $authorizer->userCanModifyDonation( self::MEANINGLESS_DONATION_ID ) );
			}
		);

		$this->specify(
			'access authorization fails',
			function (): void {
				$authorizer = $this->newAuthorizationService( self::CORRECT_ACCESS_TOKEN );
				$this->assertFalse( $authorizer->canAccessDonation( self::MEANINGLESS_DONATION_ID ) );
			}
		);
	}

	/**
	 * @slowThreshold 1200
	 */
	public function testWhenDonationWithTokenExists(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donationData->setUpdateTokenExpiry( $this->getExpiryTimeInTheFuture() );
		$donationData->setAccessToken( self::CORRECT_ACCESS_TOKEN );
		$donation->setDataObject( $donationData );
		$this->storeDonation( $donation );

		$this->specify(
			'given correct donation id and correct token, update authorization succeeds',
			function () use ( $donation ): void {
				$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );
				$this->assertTrue( $authorizer->userCanModifyDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given wrong donation id and correct token, update authorization fails',
			function (): void {
				$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );
				$this->assertFalse( $authorizer->userCanModifyDonation( self::ID_OF_WRONG_DONATION ) );
			}
		);

		$this->specify(
			'given correct donation id and wrong token, update authorization fails',
			function () use ( $donation ): void {
				$authorizer = $this->newAuthorizationService( self::WRONG__UPDATE_TOKEN );
				$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given correct donation id and correct token, access authorization succeeds',
			function () use ( $donation ): void {
				$authorizer = $this->newAuthorizationService( '', self::CORRECT_ACCESS_TOKEN );
				$this->assertTrue( $authorizer->canAccessDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given wrong donation id and correct token, access authorization fails',
			function (): void {
				$authorizer = $this->newAuthorizationService( '', self::CORRECT_ACCESS_TOKEN );
				$this->assertFalse( $authorizer->canAccessDonation( self::ID_OF_WRONG_DONATION ) );
			}
		);

		$this->specify(
			'given correct donation id and wrong token, access authorization fails',
			function () use ( $donation ): void {
				$authorizer = $this->newAuthorizationService( '', self::WRONG_ACCESS_TOKEN );
				$this->assertFalse( $authorizer->canAccessDonation( $donation->getId() ) );
			}
		);
	}

	private function getExpiryTimeInTheFuture(): string {
		return date( 'Y-m-d H:i:s', time() + 60 * 60 );
	}

	public function testGivenTokenAndLegacyDonation_updateAuthorizationFails(): void {
		$donation = new Donation();
		$this->storeDonation( $donation );
		$authorizer = $this->newAuthorizationService( self::MEANINGLESS_TOKEN );

		$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
	}

	public function testGivenTokenAndLegacyDonation_accessAuthorizationFails(): void {
		$donation = new Donation();
		$this->storeDonation( $donation );
		$authorizer = $this->newAuthorizationService( self::EMPTY_TOKEN, self::MEANINGLESS_TOKEN );

		$this->assertFalse( $authorizer->canAccessDonation( $donation->getId() ) );
	}

	public function testGivenEmptyTokenAndLegacyDonation_updateAuthorizationFails(): void {
		$donation = new Donation();
		$this->storeDonation( $donation );
		$authorizer = $this->newAuthorizationService( self::EMPTY_TOKEN, self::EMPTY_TOKEN );

		$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
	}

	public function testGivenEmptyTokenAndLegacyDonation_accessAuthorizationFails(): void {
		$donation = new Donation();
		$this->storeDonation( $donation );
		$authorizer = $this->newAuthorizationService( self::EMPTY_TOKEN, self::EMPTY_TOKEN );

		$this->assertFalse( $authorizer->canAccessDonation( $donation->getId() ) );
	}

	/**
	 * @slowThreshold 400
	 */
	public function testWhenUpdateTokenIsExpired(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donationData->setUpdateTokenExpiry( $this->getExpiryTimeInThePast() );
		$donation->setDataObject( $donationData );
		$this->storeDonation( $donation );

		$this->specify(
			'given correct donation id and a token, update authorization fails for users',
			function () use ( $donation ): void {
				$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );
				$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given correct donation id and a token, update authorization succeeds for system',
			function () use ( $donation ): void {
				$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );
				$this->assertTrue( $authorizer->systemCanModifyDonation( $donation->getId() ) );
			}
		);
	}

	private function getExpiryTimeInThePast(): string {
		return date( 'Y-m-d H:i:s', time() - 1 );
	}

	public function testGivenExceptionFromEntityManager_authorizerWrapsExceptionForUserModification(): void {
		$authorizer = new DoctrineDonationAuthorizer(
			$this->getThrowingEntityManager(),
			self::CORRECT_UPDATE_TOKEN,
			self::CORRECT_ACCESS_TOKEN
		);

		$this->expectException( GetDonationException::class );

		$authorizer->userCanModifyDonation( self::MEANINGLESS_DONATION_ID );
	}

	public function testGivenExceptionFromEntityManager_authorizerWrapsExceptionForSystemModification(): void {
		$authorizer = new DoctrineDonationAuthorizer(
			$this->getThrowingEntityManager(),
			self::CORRECT_UPDATE_TOKEN,
			self::CORRECT_ACCESS_TOKEN
		);

		$this->expectException( GetDonationException::class );

		$authorizer->systemCanModifyDonation( self::MEANINGLESS_DONATION_ID );
	}

	public function testGivenExceptionFromEntityManager_authorizerWrapsExceptionForAccessCheck(): void {
		$authorizer = new DoctrineDonationAuthorizer(
			$this->getThrowingEntityManager(),
			self::CORRECT_UPDATE_TOKEN,
			self::CORRECT_ACCESS_TOKEN
		);

		$this->expectException( GetDonationException::class );

		$authorizer->canAccessDonation( self::MEANINGLESS_DONATION_ID );
	}

	private function getThrowingEntityManager(): EntityManager {
		$entityManager = $this->getMockBuilder( EntityManager::class )
			->disableOriginalConstructor()->getMock();

		$entityManager->method( $this->anything() )
			->willThrowException( new ORMException() );

		return $entityManager;
	}

	public function testGivenExistingDonation_AuthorizerReturnsTokenSet(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donationData->setAccessToken( self::CORRECT_ACCESS_TOKEN );
		$donation->setDataObject( $donationData );
		$this->storeDonation( $donation );

		$donAuthorizer = new DoctrineDonationAuthorizer( $this->entityManager, '', '' );
		$resultTokenSet = $donAuthorizer->getTokensForDonation( $donation->getId() );

		$this->assertSame( self::CORRECT_ACCESS_TOKEN, $resultTokenSet->getAccessToken() );
		$this->assertSame( self::CORRECT_UPDATE_TOKEN, $resultTokenSet->getUpdateToken() );
	}

	public function testGivenDonationWithMissingAccessToken_AuthorizerThrowsException(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donation->setDataObject( $donationData );
		$mockEM = $this->createMock( EntityManager::class );
		$mockEM->method( 'find' )->willReturn( $donation );

		$donAuthorizer = new DoctrineDonationAuthorizer( $mockEM, '', '' );

		$this->expectException( \UnexpectedValueException::class );
		$donAuthorizer->getTokensForDonation( self::MEANINGLESS_DONATION_ID );
	}

	public function testGivenDonationWithMissingUpdateToken_AuthorizerThrowsException(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setAccessToken( self::CORRECT_ACCESS_TOKEN );
		$donation->setDataObject( $donationData );
		$mockEM = $this->createMock( EntityManager::class );
		$mockEM->method( 'find' )->willReturn( $donation );

		$donAuthorizer = new DoctrineDonationAuthorizer( $mockEM, '', '' );

		$this->expectException( \UnexpectedValueException::class );
		$donAuthorizer->getTokensForDonation( self::MEANINGLESS_DONATION_ID );
	}

	public function testGivenMissingDonation_AuthorizerThrowsException(): void {
		$mockEM = $this->createMock( EntityManager::class );
		$mockEM->method( 'find' )->willReturn( null );

		$donAuthorizer = new DoctrineDonationAuthorizer( $mockEM, '', '' );

		$this->expectException( GetDonationException::class );
		$donAuthorizer->getTokensForDonation( self::MEANINGLESS_DONATION_ID );
	}

}
