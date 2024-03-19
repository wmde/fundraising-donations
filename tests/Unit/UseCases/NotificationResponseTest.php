<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\UseCases;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\UseCases\NotificationResponse;

#[CoversClass( NotificationResponse::class )]
class NotificationResponseTest extends TestCase {
	public function testSuccessResponseHasNoErrors(): void {
		$response = NotificationResponse::newSuccessResponse();

		$this->assertTrue( $response->notificationWasHandled() );
		$this->assertFalse( $response->hasErrors() );
		$this->assertSame( '', $response->getMessage() );
	}

	public function testFailureResponseHasErrorMessage(): void {
		$response = NotificationResponse::newFailureResponse( 'These are not the payments you\'re looking for' );

		$this->assertFalse( $response->notificationWasHandled() );
		$this->assertTrue( $response->hasErrors() );
		$this->assertSame(
			'These are not the payments you\'re looking for',
			$response->getMessage()
		);
	}

	public function testFailureResponseMessageMustNotBeEmpty(): void {
		$this->expectException( \DomainException::class );

		NotificationResponse::newFailureResponse( '' );
	}

}
