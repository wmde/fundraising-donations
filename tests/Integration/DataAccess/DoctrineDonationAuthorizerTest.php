<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\DataAccess;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
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

	private const CORRECT_UPDATE_TOKEN = 'CorrectUpdateToken';
	private const CORRECT_ACCESS_TOKEN = 'CorrectAccessToken';
	private const WRONG__UPDATE_TOKEN = 'WrongUpdateToken';
	private const WRONG_ACCESS_TOKEN = 'WrongAccessToken';
	private const MEANINGLESS_TOKEN = 'Some token';
	private const EMPTY_TOKEN = '';
	private const MEANINGLESS_DONATION_ID = 1337;
	private const DUMMY_PAYMENT_ID = 23;

	private EntityManager $entityManager;

	protected function setUp(): void {
		$this->entityManager = TestEnvironment::newInstance()->getFactory()->getEntityManager();
	}

	private function newAuthorizationService( string $updateToken = '', string $accessToken = '' ): DonationAuthorizer {
		return new DoctrineDonationAuthorizer( $this->entityManager, $updateToken, $accessToken );
	}

	public function testGivenNoDonation_authorizationFails(): void {
		$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );
		$this->assertFalse( $authorizer->userCanModifyDonation( self::MEANINGLESS_DONATION_ID ) );
		$this->assertFalse( $authorizer->canAccessDonation( self::MEANINGLESS_DONATION_ID ) );
	}

	/**
	 * @dataProvider updateTokenProvider
	 */
	public function testAuthorizerChecksUpdateTokenOfDonation( string $updateToken, bool $expectedResult ): void {
		$donation = $this->givenDonationWithTokens();
		$authorizer = $this->newAuthorizationService( $updateToken );

		$this->assertSame( $expectedResult, $authorizer->userCanModifyDonation( $donation->getId() ) );
	}

	/**
	 * @return iterable<string,array{string,bool}>
	 */
	public static function updateTokenProvider(): iterable {
		yield 'correct update token' => [ self::CORRECT_UPDATE_TOKEN, true ];
		yield 'incorrect update token' => [ self::WRONG__UPDATE_TOKEN, false ];
	}

	/**
	 * @dataProvider accessTokenProvider
	 */
	public function testAuthorizerChecksAccessTokenOfDonation( string $accessToken, bool $expectedResult ): void {
		$donation = $this->givenDonationWithTokens();
		$authorizer = $this->newAuthorizationService( '', $accessToken );

		$this->assertSame( $expectedResult, $authorizer->canAccessDonation( $donation->getId() ) );
	}

	/**
	 * @return iterable<string,array{string,bool}>
	 */
	public static function accessTokenProvider(): iterable {
		yield 'correct access token' => [ self::CORRECT_ACCESS_TOKEN, true ];
		yield 'incorrect update token' => [ self::WRONG_ACCESS_TOKEN, false ];
	}

	private function getExpiryTimeInTheFuture(): string {
		return date( 'Y-m-d H:i:s', time() + 60 * 60 );
	}

	public function testGivenTokenAndLegacyDonation_updateAuthorizationFails(): void {
		$donation = $this->givenLegacyDonation();
		$authorizer = $this->newAuthorizationService( self::MEANINGLESS_TOKEN );

		$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
	}

	public function testGivenTokenAndLegacyDonation_accessAuthorizationFails(): void {
		$donation = $this->givenLegacyDonation();
		$authorizer = $this->newAuthorizationService( self::EMPTY_TOKEN, self::MEANINGLESS_TOKEN );

		$this->assertFalse( $authorizer->canAccessDonation( $donation->getId() ) );
	}

	public function testGivenEmptyTokenAndLegacyDonation_updateAuthorizationFails(): void {
		$donation = $this->givenLegacyDonation();
		$authorizer = $this->newAuthorizationService( self::EMPTY_TOKEN, self::EMPTY_TOKEN );

		$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
	}

	public function testGivenEmptyTokenAndLegacyDonation_accessAuthorizationFails(): void {
		$donation = $this->givenLegacyDonation();
		$authorizer = $this->newAuthorizationService( self::EMPTY_TOKEN, self::EMPTY_TOKEN );

		$this->assertFalse( $authorizer->canAccessDonation( $donation->getId() ) );
	}

	public function testWhenUpdateTokenIsExpiredUpdateCheckFailsForUser(): void {
		$donation = $this->givenDonationWithExpiredUpdateToken();

		$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );
		$this->assertFalse( $authorizer->userCanModifyDonation( $donation->getId() ) );
	}

	public function testWhenUpdateTokenIsExpiredUpdateCheckSucceedsForSystem(): void {
		$donation = $this->givenDonationWithExpiredUpdateToken();
		$authorizer = $this->newAuthorizationService( self::CORRECT_UPDATE_TOKEN );

		$this->assertTrue( $authorizer->systemCanModifyDonation( $donation->getId() ) );
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

	private function givenDonationWithTokens(): Donation {
		$donation = new Donation();
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donationData->setUpdateTokenExpiry( $this->getExpiryTimeInTheFuture() );
		$donationData->setAccessToken( self::CORRECT_ACCESS_TOKEN );
		$donation->setDataObject( $donationData );
		$this->storeDonation( $donation );
		return $donation;
	}

	private function givenDonationWithExpiredUpdateToken(): Donation {
		$donation = new Donation();
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donationData->setUpdateTokenExpiry( $this->getExpiryTimeInThePast() );
		$donation->setDataObject( $donationData );
		$this->storeDonation( $donation );
		return $donation;
	}

	private function givenLegacyDonation(): Donation {
		$donation = new Donation();
		$donation->setPaymentId( self::DUMMY_PAYMENT_ID );
		$this->storeDonation( $donation );
		return $donation;
	}

	private function storeDonation( Donation $donation ): void {
		$this->entityManager->persist( $donation );
		$this->entityManager->flush();
	}

	private function getExpiryTimeInThePast(): string {
		return date( 'Y-m-d H:i:s', time() - 1 );
	}
}
