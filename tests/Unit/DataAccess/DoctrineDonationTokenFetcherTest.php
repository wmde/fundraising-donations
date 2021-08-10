<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\DataAccess;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Authorization\DonationTokenFetchingException;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationAuthorizer;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationTokenFetcher;
use WMDE\Fundraising\DonationContext\DataAccess\DoctrineEntities\Donation;
use WMDE\Fundraising\DonationContext\Domain\Repositories\GetDonationException;
use WMDE\Fundraising\DonationContext\Tests\TestEnvironment;

/**
 * @covers \WMDE\Fundraising\DonationContext\DataAccess\DoctrineDonationTokenFetcher
 */
class DoctrineDonationTokenFetcherTest extends TestCase {
	private const CORRECT_UPDATE_TOKEN = 'CorrectUpdateToken';
	private const CORRECT_ACCESS_TOKEN = 'CorrectAccessToken';
	private const DONATION_ID = 1;
	private const MEANINGLESS_DONATION_ID = 1337;

	/**
	 * @var EntityManager&\PHPUnit\Framework\MockObject\MockObject
	 */
	private EntityManager $entityManager;

	protected function setUp(): void {
		$this->entityManager = $this->createMock( EntityManager::class );
	}

	public function testGivenExistingDonation_AuthorizerReturnsTokenSet(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donationData->setAccessToken( self::CORRECT_ACCESS_TOKEN );
		$donation->setDataObject( $donationData );
		$this->entityManager->method( 'find' )->willReturn( $donation );

		$donAuthorizer = new DoctrineDonationTokenFetcher( $this->entityManager );
		$resultTokenSet = $donAuthorizer->getTokens( self::DONATION_ID );

		$this->assertSame( self::CORRECT_ACCESS_TOKEN, $resultTokenSet->getAccessToken() );
		$this->assertSame( self::CORRECT_UPDATE_TOKEN, $resultTokenSet->getUpdateToken() );
	}

	public function testGivenDonationWithMissingAccessToken_AuthorizerThrowsException(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setUpdateToken( self::CORRECT_UPDATE_TOKEN );
		$donation->setDataObject( $donationData );
		$this->entityManager->method( 'find' )->willReturn( $donation );

		$donAuthorizer = new DoctrineDonationTokenFetcher( $this->entityManager );

		$this->expectException( DonationTokenFetchingException::class );
		$donAuthorizer->getTokens( self::MEANINGLESS_DONATION_ID );
	}

	public function testGivenDonationWithMissingUpdateToken_AuthorizerThrowsException(): void {
		$donation = new Donation();
		$donationData = $donation->getDataObject();
		$donationData->setAccessToken( self::CORRECT_ACCESS_TOKEN );
		$donation->setDataObject( $donationData );
		$this->entityManager->method( 'find' )->willReturn( $donation );

		$donAuthorizer = new DoctrineDonationTokenFetcher( $this->entityManager );

		$this->expectException( DonationTokenFetchingException::class );
		$donAuthorizer->getTokens( self::MEANINGLESS_DONATION_ID );
	}

	public function testGivenMissingDonation_AuthorizerThrowsException(): void {
		$this->entityManager->method( 'find' )->willReturn( null );

		$donAuthorizer = new DoctrineDonationTokenFetcher( $this->entityManager );

		$this->expectException( DonationTokenFetchingException::class );
		$donAuthorizer->getTokens( self::MEANINGLESS_DONATION_ID );
	}
}
