<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\DonationContext\Tests\Integration\DataAccess;

use Codeception\Specify;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use WMDE\Fundraising\Entities\Donation;
use WMDE\Fundraising\Frontend\DonationContext\Authorization\DonationAuthorizer;
use WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationAuthorizer;
use WMDE\Fundraising\Frontend\DonationContext\Tests\TestEnvironment;

/**
 * @covers WMDE\Fundraising\Frontend\DonationContext\DataAccess\DoctrineDonationAuthorizer
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DoctrineDonationAuthorizerTest extends \PHPUnit\Framework\TestCase {
	use Specify;

	private const CORRECT_UPDATE_TOKEN = 'CorrectUpdateToken';
	private const CORRECT_ACCESS_TOKEN = 'CorrectAccessToken';
	private const WRONG__UPDATE_TOKEN = 'WrongUpdateToken';
	private const WRONG_ACCESS_TOKEN = 'WrongAccessToken';
	private const MEANINGLESS_TOKEN = 'Some token';
	private const MEANINGLESS_DONATION_ID = 1337;
	private const ID_OF_WRONG_DONATION = 42;

	private function newAuthorizationServiceWithDonations( string $updateToken = null,
		string $accessToken = null, Donation ...$donations ): DonationAuthorizer {

		$entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();

		foreach ( $donations as $donation ) {
			$entityManager->persist( $donation );
		}

		$entityManager->flush();

		return new DoctrineDonationAuthorizer( $entityManager, $updateToken, $accessToken );
	}

	/**
	 * @slowThreshold 400
	 */
	public function testWhenNoDonations(): void {
		$this->specify( 'update authorization fails', function() {
			$authorizer = $this->newAuthorizationServiceWithDonations( self::CORRECT_UPDATE_TOKEN );
			$this->assertFalse( $authorizer->userCanModifyDonation( self::MEANINGLESS_DONATION_ID ) );
		} );

		$this->specify( 'access authorization fails', function() {
			$authorizer = $this->newAuthorizationServiceWithDonations( self::CORRECT_ACCESS_TOKEN );
			$this->assertFalse( $authorizer->canAccessDonation( self::MEANINGLESS_DONATION_ID ) );
		} );
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

		$this->specify(
			'given correct donation id and correct token, update authorization succeeds',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( self::CORRECT_UPDATE_TOKEN, null, $donation );
				$this->assertTrue( $authorizer->userCanModifyDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given wrong donation id and correct token, update authorization fails',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( self::CORRECT_UPDATE_TOKEN, null, $donation );
				$this->assertFalse( $authorizer->userCanModifyDonation( self::ID_OF_WRONG_DONATION ) );
			}
		);

		$this->specify(
			'given correct donation id and wrong token, update authorization fails',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( self::WRONG__UPDATE_TOKEN, null, $donation );
				$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given correct donation id and correct token, access authorization succeeds',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( null, self::CORRECT_ACCESS_TOKEN, $donation );
				$this->assertTrue( $authorizer->canAccessDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given wrong donation id and correct token, access authorization fails',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( null, self::CORRECT_ACCESS_TOKEN, $donation );
				$this->assertFalse( $authorizer->canAccessDonation( self::ID_OF_WRONG_DONATION ) );
			}
		);

		$this->specify(
			'given correct donation id and wrong token, access authorization fails',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( null, self::WRONG_ACCESS_TOKEN, $donation );
				$this->assertFalse( $authorizer->canAccessDonation( $donation->getId() ) );
			}
		);
	}

	private function getExpiryTimeInTheFuture(): string {
		return date( 'Y-m-d H:i:s', time() + 60 * 60 );
	}

	/**
	 * @slowThreshold 400
	 */
	public function testWhenDonationWithoutTokenExists(): void {
		$donation = new Donation();

		$this->specify(
			'given correct donation id and a token, update authorization fails',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( self::MEANINGLESS_TOKEN, null, $donation );
				$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given correct donation id and a token, access authorization fails',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( null, self::MEANINGLESS_TOKEN, $donation );
				$this->assertFalse( $authorizer->canAccessDonation( $donation->getId() ) );
			}
		);
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

		$this->specify(
			'given correct donation id and a token, update authorization fails for users',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( self::CORRECT_UPDATE_TOKEN, null, $donation );
				$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
			}
		);

		$this->specify(
			'given correct donation id and a token, update authorization succeeds for system',
			function() use ( $donation ) {
				$authorizer = $this->newAuthorizationServiceWithDonations( self::CORRECT_UPDATE_TOKEN, null, $donation );
				$this->assertTrue( $authorizer->systemCanModifyDonation( $donation->getId() ) );
			}
		);
	}

	private function getExpiryTimeInThePast(): string {
		return date( 'Y-m-d H:i:s', time() - 1 );
	}

	/**
	 * @slowThreshold 400
	 */
	public function testWhenDoctrineThrowsException(): void {
		$authorizer = new DoctrineDonationAuthorizer(
			$this->getThrowingEntityManager(),
			self::CORRECT_UPDATE_TOKEN,
			self::CORRECT_ACCESS_TOKEN
		);

		$this->specify( 'update authorization fails', function() use ( $authorizer ) {
			$this->assertFalse( $authorizer->userCanModifyDonation( self::MEANINGLESS_DONATION_ID ) );
		} );

		$this->specify( 'access authorization fails', function() use ( $authorizer ) {
			$this->assertFalse( $authorizer->canAccessDonation( self::MEANINGLESS_DONATION_ID ) );
		} );
	}

	private function getThrowingEntityManager(): EntityManager {
		$entityManager = $this->getMockBuilder( EntityManager::class )
			->disableOriginalConstructor()->getMock();

		$entityManager->method( $this->anything() )
			->willThrowException( new ORMException() );

		return $entityManager;
	}

}
