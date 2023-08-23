<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\DonationContext\Tests\Integration\UseCases\GetDonation;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FailingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\FakeDonationRepository;
use WMDE\Fundraising\DonationContext\Tests\Fixtures\SucceedingDonationAuthorizer;
use WMDE\Fundraising\DonationContext\UseCases\GetDonation\GetDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\GetDonation\GetDonationUseCase;

/**
 * @covers \WMDE\Fundraising\DonationContext\UseCases\GetDonation\GetDonationUseCase
 */
class GetDonationUseCaseTest extends TestCase {

	private const CORRECT_DONATION_ID = 1;

	public function testWhenAuthorizerSaysNoCanHaz_accessIsNotPermitted(): void {
		$useCase = new GetDonationUseCase(
			new FailingDonationAuthorizer(),
			new FakeDonationRepository( ValidDonation::newDirectDebitDonation() )
		);

		$response = $useCase->showConfirmation(
			new GetDonationRequest(
				self::CORRECT_DONATION_ID
			)
		);

		$this->assertFalse( $response->accessIsPermitted() );
		$this->assertNull( $response->getDonation() );
	}

	public function testWhenAuthorizerSaysSureThingBro_accessIsPermitted(): void {
		$useCase = new GetDonationUseCase(
			new SucceedingDonationAuthorizer(),
			new FakeDonationRepository( ValidDonation::newDirectDebitDonation() )
		);

		$response = $useCase->showConfirmation(
			new GetDonationRequest(
				self::CORRECT_DONATION_ID
			)
		);

		$this->assertTrue( $response->accessIsPermitted() );
	}

	public function testWhenDonationDoesNotExist_accessIsNotPermitted(): void {
		$useCase = new GetDonationUseCase(
			new SucceedingDonationAuthorizer(),
			new FakeDonationRepository()
		);

		$response = $useCase->showConfirmation(
			new GetDonationRequest(
				self::CORRECT_DONATION_ID
			)
		);

		$this->assertFalse( $response->accessIsPermitted() );
		$this->assertNull( $response->getDonation() );
	}

	public function testWhenDonationExistsAndAccessIsAllowed_donationIsReturned(): void {
		$donation = ValidDonation::newDirectDebitDonation();

		$useCase = new GetDonationUseCase(
			new SucceedingDonationAuthorizer(),
			new FakeDonationRepository( $donation )
		);

		$response = $useCase->showConfirmation(
			new GetDonationRequest(
				self::CORRECT_DONATION_ID
			)
		);

		$this->assertEquals( $donation, $response->getDonation() );
	}
}
