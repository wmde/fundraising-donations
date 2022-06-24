<?php
declare( strict_types=1 );

namespace WMDE\Fundraising\DonationContext\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationIdentifier;
use WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason;

/**
 * @covers \WMDE\Fundraising\DonationContext\Domain\Model\ModerationReason
 */
class ModerationReasonTest extends TestCase {
	public function testObjectGetsBuiltCorrectly(): void {
		$reason = new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' );

		$this->assertSame( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, $reason->getModerationIdentifier() );
		$this->assertSame( 'email', $reason->getSource() );
	}

	/**
	 * @dataProvider stringifyProvider
	 */
	public function testStringify( ModerationReason $reason, string $expected ): void {
		$this->assertSame( $expected, (string)$reason );
	}

	/**
	 * @return iterable<string,array{ModerationReason,string}>
	 */
	public function stringifyProvider(): iterable {
		yield 'ModerationIdentifier and source' => [
			new ModerationReason( ModerationIdentifier::ADDRESS_CONTENT_VIOLATION, 'email' ),
			'ADDRESS_CONTENT_VIOLATION:email'
		];
		yield 'ModerationIdentifier without source' => [
			new ModerationReason( ModerationIdentifier::MANUALLY_FLAGGED_BY_ADMIN ),
			'MANUALLY_FLAGGED_BY_ADMIN'
		];
	}
}
